<?php
namespace Modules\RestApi\Models;

use Core\BaseModel;

class RestApiModel extends BaseModel
{
    /**
     * API Token doğrulama ve kullanıcı çekme
     */
    public function getUserByToken(string $token): ?object
    {
        $sql = "SELECT id, name, email, role, avatar_color, api_token FROM users WHERE api_token = ? LIMIT 1";
        $user = $this->first($sql, [$token]);
        return $user ?: null;
    }

    /**
     * Token ile proje kontrolü
     */
    public function getProjectByCode(string $code): ?object
    {
        $sql = "SELECT * FROM projects WHERE project_code = ? LIMIT 1";
        $proj = $this->first($sql, [$code]);
        return $proj ?: null;
    }
}
