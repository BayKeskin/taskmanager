<?php

class Content
{
    private Database $db;
    private array $fieldCache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * ACF Benzeri Özel Alan (Custom Field) Değer Okuyucu
     * In-memory cache ile N+1 sorgu problemi önlenmiştir.
     */
    public function return_value($post_id, $name)
    {
        $cacheKey = "{$post_id}_{$name}";
        if (array_key_exists($cacheKey, $this->fieldCache)) {
            return $this->fieldCache[$cacheKey];
        }

        $row = $this->db->row_query("SELECT fgp.value, fg.type, fg.multiple 
                                     FROM field_groups_provision fgp
                                     LEFT JOIN field_groups fg ON fg.id = fgp.field_groups_id
                                     WHERE fgp.name = ? AND fgp.post_id = ?
                                     LIMIT 1", [$name, $post_id]);

        if (!$row) {
            $this->fieldCache[$cacheKey] = null;
            return null;
        }

        $parsedValue = $this->parseFieldValue($row->type, $row->value, (int)($row->multiple ?? 0));
        $this->fieldCache[$cacheKey] = $parsedValue;
        return $parsedValue;
    }

    /**
     * Ham veritabanı değerini alan tipine göre parse eder
     */
    private function parseFieldValue(?string $type, $val, int $multiple = 0)
    {
        if ($val === null || $val === '') {
            return $val;
        }

        switch ($type) {
            case 'textarea-line':
            case 'repeater':
                $decoded = json_decode((string)$val, true);
                return is_array($decoded) ? $decoded : [];

            case 'image':
                if ($multiple === 1) {
                    $decoded = json_decode((string)$val, true);
                    return is_array($decoded) ? $decoded : [];
                }
                return $val;

            case 'switcher':
                return ((string)$val === '1' || $val === 1 || $val === true);

            case 'number':
                return is_numeric($val) ? (float)$val : $val;

            default:
                return $val;
        }
    }

    /**
     * Tek bir yazıyı tüm özel alanlarıyla birlikte tek seferde çeker (Eager-Loading)
     *
     * @param int $id Post ID
     * @return object|null $post nesnesi ve $post->fields altındaki tüm özel alanlar
     */
    public function get_post_with_fields(int $id)
    {
        $post = $this->db->row_query("SELECT * FROM post WHERE id = ? LIMIT 1", [$id]);
        if (!$post) {
            return null;
        }

        $fields = $this->db->multi_record_query(
            "SELECT fgp.name, fgp.value, fg.type, fg.multiple 
             FROM field_groups_provision fgp
             LEFT JOIN field_groups fg ON fg.id = fgp.field_groups_id
             WHERE fgp.post_id = ?",
            [$id]
        ) ?: [];

        $post->fields = new stdClass();
        foreach ($fields as $f) {
            $parsed = $this->parseFieldValue($f->type ?? 'text', $f->value, (int)($f->multiple ?? 0));
            $post->fields->{$f->name} = $parsed;
            $this->fieldCache["{$id}_{$f->name}"] = $parsed;
        }

        return $post;
    }

    /**
     * Belirli bir tipe ait yazıları tüm özel alanlarıyla birlikte 2 sorguda çeker (Sayfalama Destekli)
     *
     * @param string $type Post type (page, blog, product vb.)
     * @param int $limit Maksimum kayıt sayısı (Sayfa başına adet)
     * @param int $offset Başlangıç konumu (Sayfalama için: ($page - 1) * $limit)
     * @return array Post nesneleri dizisi
     */
    public function get_posts_with_fields(string $type, int $limit = 50, int $offset = 0): array
    {
        $posts = $this->db->multi_record_query(
            "SELECT * FROM post WHERE type = ? ORDER BY id DESC LIMIT ? OFFSET ?",
            [$type, $limit, $offset]
        ) ?: [];

        return $this->attach_fields($posts);
    }

    /**
     * Herhangi bir post dizisine (örneğin kategori sorgusu veya arama sonucu)
     * tek seferde tüm özel alanları ekler (Eager Loading - N+1 Çözümü)
     *
     * @param array $posts stdClass post nesneleri dizisi
     * @return array $post->fields doldurulmuş post nesneleri
     */
    public function attach_fields(array $posts): array
    {
        if (empty($posts)) {
            return [];
        }

        $postIds = [];
        foreach ($posts as $p) {
            if (isset($p->id)) {
                $postIds[] = (int)$p->id;
            }
        }

        if (empty($postIds)) {
            return $posts;
        }

        $inPlaceholders = implode(',', array_fill(0, count($postIds), '?'));
        $allFields = $this->db->multi_record_query(
            "SELECT fgp.post_id, fgp.name, fgp.value, fg.type, fg.multiple 
             FROM field_groups_provision fgp
             LEFT JOIN field_groups fg ON fg.id = fgp.field_groups_id
             WHERE fgp.post_id IN ({$inPlaceholders})",
            $postIds
        ) ?: [];

        // Post ID'ye göre grupla
        $groupedFields = [];
        foreach ($allFields as $f) {
            $parsed = $this->parseFieldValue($f->type ?? 'text', $f->value, (int)($f->multiple ?? 0));
            $groupedFields[$f->post_id][$f->name] = $parsed;
            $this->fieldCache["{$f->post_id}_{$f->name}"] = $parsed;
        }

        foreach ($posts as $post) {
            $post->fields = new stdClass();
            if (isset($groupedFields[$post->id])) {
                foreach ($groupedFields[$post->id] as $fname => $fval) {
                    $post->fields->{$fname} = $fval;
                }
            }
        }

        return $posts;
    }

    /**
     * Sistem genel ayarlarını çeker
     */
    public function get_settings($settings_name)
    {
        return $this->db->get_single('field_groups_provision', 'value', 'name', $settings_name);
    }

    /**
     * Konuma göre navigasyon (menü) elemanlarını çeker
     * 1: Header, 2: Footer
     */
    public function navs($location)
    {
        $position = ($location == 1) ? 'Header' : (($location == 2) ? 'Footer' : null);

        if ($position) {
            return $this->db->multi_record_query("SELECT p.* FROM post p 
                     INNER JOIN field_groups_provision fgp ON fgp.post_id = p.id 
                     WHERE p.type = 'navs' AND fgp.name = 'navs_position' AND fgp.value = ?
                     ORDER BY p.id ASC", [$position]) ?: [];
        }

        $query = "SELECT p.* FROM post p WHERE p.type = 'navs' ORDER BY p.id ASC";
        return $this->db->multi_record_query($query) ?: [];
    }

    /**
     * Menü başlığını döner
     */
    public function return_nav_title($nav_id)
    {
        $lang = class_exists('Language') ? Language::getCurrentLang() : 'tr';
        return $this->db->get_single('post', 'title_' . $lang, 'id', $nav_id)
            ?: ($this->db->get_single('post', 'title_tr', 'id', $nav_id)
            ?: $this->db->get_single('post', 'title', 'id', $nav_id));
    }

    /**
     * Alt menüsü olup olmadığını denetler
     */
    public function is_nav_parent($nav_id)
    {
        $rows = $this->db->multi_record_query("SELECT * FROM field_groups_provision WHERE value = ? AND name = 'navs_parent_id'", [(string) $nav_id]) ?: [];
        return !empty($rows) ? $rows : false;
    }

}

/**
 * Global ACF Benzeri Kolay Yardımcı Fonksiyonlar (Helper Functions)
 */
if (!function_exists('get_field')) {
    /**
     * Özel alan değerini otomatik parse edilmiş olarak döner
     *
     * @param string $name Alan adı (slug)
     * @param int|null $postId Yazı ID (verilmezse mevcut sayfanın ID'si alınır)
     * @return mixed
     */
    function get_field(string $name, $postId = null)
    {
        global $system, $post;
        if ($postId === null) {
            $postId = !empty($post->id) ? (int)$post->id : (int)Helper::get('id', 0);
        }
        return $system ? $system->return_value((int)$postId, $name) : null;
    }
}

if (!function_exists('the_field')) {
    /**
     * Özel alan değerini güvenli şekilde ekrana yazdırır
     */
    function the_field(string $name, $postId = null, bool $escape = true): void
    {
        $val = get_field($name, $postId);
        if (is_array($val) || is_object($val)) {
            echo json_encode($val, JSON_UNESCAPED_UNICODE);
        } else {
            echo $escape ? htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') : (string)$val;
        }
    }
}

if (!function_exists('get_post_fields')) {
    /**
     * Bir yazının tüm özel alanlarını tek seferde nesne olarak döner ($post->fields)
     */
    function get_post_fields(int $postId)
    {
        global $system;
        $postObj = $system ? $system->get_post_with_fields($postId) : null;
        return $postObj ? ($postObj->fields ?? new stdClass()) : new stdClass();
    }
}
