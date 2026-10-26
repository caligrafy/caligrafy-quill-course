<?php
/**
 * Project.php
 * A Showcase project. Table: projects (see create_db.sql in this folder)
 */
use Caligrafy\Model;

class Project extends Model {

    public $id;
    public $title;
    public $description;
    public $user_id;
    public $created_at;
    public $modified_at;

    // projects.user_id -> users.id
    public function owner()
    {
        return $this->hasOne('User', 'users');
    }
}
