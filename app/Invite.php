<?php

namespace CaptainCore;

class Invite {

    protected $invite_id = "";

    public function __construct( $invite_id = "" ) {
        $this->invite_id = $invite_id;
    }

    public function get() {
        $invite = (new Invites)->get( $this->invite_id );
        return $invite;
    }

    public function mark_accepted() {
        $db       = new Invites;
        $time_now = date("Y-m-d H:i:s");
        $db->update(
            [ 'accepted_at' => $time_now ],
            [ 'invite_id'   => $this->invite_id ]
        );
        return true;
    }

}