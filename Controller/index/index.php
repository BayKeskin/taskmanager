<?php

require_once __DIR__ . '/../Controller.php';

class Index extends Controller
{
    /**
     * Anasayfa Slider Ürünlerini Çeker
     */
    public function homePageSlider()
    {
        $query = "SELECT value FROM `field_groups_provision` 
                  WHERE post_id = 11 
                  AND name IN (
                      'slider_1_product',
                      'slider_2_product',
                      'slider_3_product',
                      'slider_4_product',
                      'slider_5_product',
                      'slider_6_product'
                  )";
        return $this->db->multi_record_query($query);
    }

    /**
     * Anasayfa Kategorilerini Çeker
     */
    public function homePageCategory()
    {
        return $this->db->multi_record('post', ['type'], ['category']);
    }

    /**
     * Anasayfa Ürünlerini Çeker
     */
    public function homePageProduct()
    {

        $cats = [];
        for ($i = 1; $i <= 5; $i++) {
            $catId = $this->system->content->return_value(11, "home_page_product_{$i}_cat");
            if (!empty($catId)) {
                $cats[] = (int) $catId;
            }
        }

        // Eğer kategori tanımlı değilse sorgu çalıştırmadan boş dizi dön
        if (empty($cats)) {
            return [];
        }

        $placeholders = implode(',', $cats);
        $query = "SELECT post_id as product_id FROM field_groups_provision 
                  WHERE name = 'product_category' AND value IN ({$placeholders})";

        return $this->db->multi_record_query($query);
    }

    /**
     * Kategori Başlığını Çeker
     */
    public function returnCategoryName($cat_id)
    {
        $lang = class_exists('Language') ? Language::current() : 'tr';
        $title = $this->db->get_single('post', 'title_' . $lang, 'id', (int) $cat_id);
        if (empty($title)) {
            $title = $this->db->get_single('post', 'title_tr', 'id', (int) $cat_id);
        }
        if (empty($title)) {
            $title = $this->db->get_single('post', 'title', 'id', (int) $cat_id);
        }
        return $title ?: '';
    }
}