<?php

class mod_groupalloc_group {
    protected $main;
    protected $groupid;
    protected $group;
    protected $members = null;

    public function __construct(mod_groupalloc_main $main, $group) {
        $this->main = $main;
        if (is_object($group)) {
            $this->group = $group;
            $this->groupid = $group->id;
        } else {
            $this->groupid = $group;
        }
    }

    protected function retrieve() {
        global $DB;
        if ($this->group === null) {
            $this->group = $DB->get_record('groups', ['id' => $this->groupid]);
        }
    }

    public function get_formatted_name() {
        $this->retrieve();
        $coursecontext = $this->main->get_cm()->context->get_course_context();
        return format_string($this->group->name, true, $coursecontext);
    }

    public function get_id() {
        return $this->groupid;
    }

    public function get_members() {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        if ($this->members === null) {
            $this->members = groups_get_members($this->groupid, user_picture::fields('u'));
        }
        return $this->members;
    }

    public function is_member($user = null) {
        global $USER;
        $user = $user ?: $USER;
        $members = $this->get_members();
        return array_key_exists($user->id, $members);
    }

    public function add_member($user) {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        $this->get_members();
        if (!array_key_exists($user->id, $this->members)) {
            groups_add_member($this->groupid, $user);
            $this->members[$user->id] = $user;
        }
    }

    public function remove_member($user) {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        $this->get_members();
        if (array_key_exists($user->id, $this->members)) {
            groups_remove_member($this->groupid, $user);
            unset($this->members[$user->id]);
        }
    }
}