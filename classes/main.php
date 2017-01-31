<?php


defined('MOODLE_INTERNAL') || die();

class mod_groupalloc_main {
    protected $cm;
    protected $activityrecord;
    protected $config = null;
    protected $groups = null;

    const CONFIG_AUTOREMOVE = 'autoremove';
    const CONFIG_MAXGROUPS = 'maxgroups';
    const CONFIG_MINMEMBERS = 'minmembers';
    const CONFIG_MAXMEMBERS = 'maxmembers';

    public function __construct(cm_info $cm, $activityrecord) {
        $this->cm = $cm;
        $this->activityrecord = $activityrecord;
    }

    public function get_cm() {
        return $this->cm;
    }

    public function get_activity_record() {
        return $this->activityrecord;
    }

    public function get_config($key) {
        if ($this->config === null) {
            $this->config = json_decode($this->activityrecord->config, true);
        }
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        return null;
    }

    public function get_groupingid() {
        return $this->activityrecord->usegroupingid;
    }

    public function can_create_empty_group() {
        if ($this->get_config(self::CONFIG_AUTOREMOVE)) {
            return false;
        }
        return has_capability('moodle/course:managegroups', $this->cm->context);
    }

    public function can_create_and_join_group() {
        return has_any_capability(['mod/groupalloc:creategroup', 'mod/groupalloc:createpasswordgroup',
            'moodle/course:managegroups'], $this->cm->context) &&
            $this->can_join_group();
    }

    public function can_leave_group($group) {
        if (!has_capability('mod/groupalloc:leavegroup', $this->cm->context)) {
            return false;
        }
        $group = $this->get_group($group, IGNORE_MISSING);
        if ($group && !$group->is_member()) {
            // Not a member.
            return false;
        }
        $minmembers = $this->get_config(self::CONFIG_MINMEMBERS);
        if ($minmembers) {
            $members = $group->get_members();
            if (count($members) <= $minmembers) {
                // Can not leave incomplete group.
                return false;
            }
        }
        return true;
    }

    public function can_join_group($group = null) {
        if (!has_capability('mod/groupalloc:joingroup', $this->cm->context)) {
            return false;
        }
        // TODO check user is enrolled.
        if ($group = $this->get_group($group)) {
            $maxmembers = $this->get_config(self::CONFIG_MAXMEMBERS);
            if ($group->is_member()) {
                // Already a member.
                return false;
            }
            if ($maxmembers && ($members = $group->get_members()) && count($members) >= $maxmembers) {
                // Group is full.
                return false;
            }
        }
        $maxgroups = $this->get_config(self::CONFIG_MAXGROUPS);
        if (!$maxgroups) {
            return true;
        }
        $usergroups = $this->get_user_groups();
        return count($usergroups) < $maxgroups;
    }

    public function get_user_groups($user = null) {
        $usergroups = [];
        $groups = $this->get_groups();
        foreach ($groups as $id => $group) {
            if ($group->is_member($user)) {
                $usergroups[$id] = $group;
            }
        }
        return $usergroups;
    }

    protected function create_group($name) {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        $data = (object)[
            'name' => $name,
            'courseid' => $this->cm->course
        ];
        $groupid = groups_create_group($data);
        if ($groupingid = $this->get_groupingid()) {
            groups_assign_grouping($groupingid, $groupid);
        }
        return new mod_groupalloc_group($this, $groupid);
    }

    public function get_groups() {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        if ($this->groups !== null) {
            return $this->groups;
        }
        $groupingid = $this->get_groupingid();
        $groupsraw = groups_get_all_groups($this->cm->course, null, $groupingid);
        $this->groups = [];
        foreach ($groupsraw as $id => $data) {
            $this->groups[$id] = new mod_groupalloc_group($this, $data);
        }
        return $this->groups;
    }

    /**
     * @param mod_groupalloc_group $group
     * @param stdClass $user
     */
    protected function join_group($group, $user = null) {
        global $CFG, $USER;
        require_once($CFG->dirroot.'/group/lib.php');
        if ($user === null) {
            $user = $USER;
        }
        $group->add_member($user);
    }

    protected function leave_group($group, $user = null) {
        global $CFG, $USER;
        require_once($CFG->dirroot.'/group/lib.php');
        if ($user === null) {
            $user = $USER;
        }
        $group->remove_member($user);
        $this->check_remove_empty_group($group);
    }

    protected function check_remove_empty_group($group) {
        global $CFG;
        require_once($CFG->dirroot.'/group/lib.php');
        // TODO check this group was created inside this module
        if (!$this->get_config(self::CONFIG_AUTOREMOVE)) {
            return;
        }
        $members = $group->get_members();
        if (empty($members)) {
            groups_delete_group($group->get_id());
        }
        unset($this->groups[$group->get_id()]);
    }

    /**
     * @param int|stdClass|mod_groupalloc_group $groupid
     * @return mod_groupalloc_group|null
     */
    protected function get_group($grouporid, $strictness = MUST_EXIST) {
        if ($grouporid === null) {
            return null;
        }
        $groups = $this->get_groups();
        if ($grouporid instanceof mod_groupalloc_group) {
            $grouporid = $grouporid->get_id();
        } else if (is_object($grouporid)) {
            $grouporid = $grouporid->id;
        }
        if (array_key_exists($grouporid, $groups)) {
            return $groups[$grouporid];
        }
        if ($strictness == MUST_EXIST) {
            throw new moodle_exception('Group not found!');
        }
        return null;
    }

    public function process_nonjs_actions() {
        $createempty = optional_param('createempty', null, PARAM_TEXT);
        $createjoin = optional_param('createjoin', null, PARAM_TEXT);
        $newgroupname = optional_param('newgroupname', null, PARAM_TEXT); // TODO Validate length and check security
        $newgroupname = trim($newgroupname);

        if ($createempty && $newgroupname && confirm_sesskey() && $this->can_create_empty_group()) {
            $group = $this->create_group($newgroupname);
            redirect($this->cm->url, 'Group '.$group->get_formatted_name().' created');
        }

        if ($createjoin && $newgroupname && confirm_sesskey() && $this->can_create_and_join_group()) {
            $group = $this->create_group($newgroupname);
            $this->join_group($group);
            redirect($this->cm->url, 'You have created and joined the group '.$group->get_formatted_name());
        }

        $joingroup = optional_param('joingroup', null, PARAM_INT);
        $leavegroup = optional_param('leavegroup', null, PARAM_INT);

        if ($joingroup && confirm_sesskey() && ($group = $this->get_group($joingroup)) && $this->can_join_group($group)) {
            $this->join_group($group);
            redirect($this->cm->url, 'You have joined the group '.$group->get_formatted_name());
        }

        if ($leavegroup && confirm_sesskey() && ($group = $this->get_group($leavegroup)) && $this->can_leave_group($group)) {
            $this->leave_group($group);
            redirect($this->cm->url, 'You have left the group '.$group->get_formatted_name());
        }

    }
}