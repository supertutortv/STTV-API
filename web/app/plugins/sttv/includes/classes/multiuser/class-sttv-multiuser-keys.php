<?php

namespace STTV\Multiuser;

defined( 'ABSPATH' ) || exit;

class Keys {

    const MIN_KEYS = 3;

    private $keys;

    private $created_keys = [];

    private $tokens = [];

    private $token = '';

    private $current_key = [];

    private $start_time;

    private $root_user;

    private $course_id;

    private $valid = false;

    private static $table = 'sttvapp_mu_keys';

    public function __construct( $user_id = 0, $course_id = 0 ) {
        $this->start_time = time();

        if ( is_string( $user_id ) ) {
            $this->set_current_key( $user_id );
            $user_id = 0;
        }
        
        $this->root_user = $user_id;
        $this->course_id = $course_id;
        return $this;
    }

    public static function getAll() {
        if ( current_user_can( 'manage_options' ) ) {
            global $wpdb;
            $table = self::$table;
            return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        }
    }

    public function get() {
        global $wpdb;
        return $wpdb->get_results("SELECT * self::FROM $table WHERE root_user = $this->root_user", ARRAY_A);
    }

    public function keygen( $qty = 0 ) {
        $qty = (int) $qty;
        if ( $qty < self::MIN_KEYS ) return null;

        $prefix = array_merge(range('A','Z'),range('a','z'));
        array_unshift($prefix,'\t');

        global $wpdb; 
        
        for ($i = 1; $i <= $qty;) {
            $key = sttv_uid( $prefix[ date( 'n' ) ].$prefix[ date( 'y' ) ].'-', openssl_random_pseudo_bytes( 32 ), true, 32 );

            $insert = [
                'mu_key' => $key,
                'root_user' => $this->root_user,
                'active_user' => 0,
                'date_created' => $this->start_time,
                'date_activated' => 0,
                'date_expires' => strtotime( '+1 year', $this->start_time ),
                'course_id' => $this->course_id
            ];

            if ( false === $wpdb->insert(self::$table,$insert) ) continue;
            $this->tokens[] = $key;
            $i++;
        }

        return $this->get_tokens();
    }

    public function activate( $user_id = 0, $time = MONTH_IN_SECONDS * 6 ) {
        if ( $this->valid )
            return $this->set_active_user( $user_id )
                ->set_activated_time()
                ->set_access_expiration( $time )
                ->update()
                ->get_current_key();
        else
            return $this->valid;
    }

    public function validate() {
        if ( empty($this->current_key) ) return $this->valid;

        if ( $this->current_key['date_expires'] < $this->start_time )
            $this->invalidate()->update();
        elseif ( $this->current_key[ 'date_activated' ] === 0 && $this->current_key['active_user'] === 0 )
            $this->valid = true;

        return $this->valid;
    }

    public function reset() {
        if ( empty($this->current_key) || $this->current_key[ 'date_activated' ] < 1 ) return false;

        return $this->set_active_user()
            ->set_access_expiration(YEAR_IN_SECONDS)
            ->set_activated_time(0)
            ->update()
            ->get_current_key();
    }

    private function invalidate() {
        $this->current_key['date_expires'] = 0;
        $this->valid = false;
        return $this;
    }

    private function update() {
        global $wpdb;
        $wpdb->update(self::$table,$this->current_key,['mu_key'=>$this->token]);
        return $this;
    }

    private function delete( $key ){
        global $wpdb;
        return $wpdb->delete(self::$table,['mu_key'=>$key]);
    }

    public function is_subscribed( $active_user = 0 ) {
        global $wpdb; $sub = false; $table = self::$table;
        $courses = $wpdb->get_results("SELECT course_id FROM $table WHERE active_user = $active_user;",ARRAY_N);
        foreach ( $courses as $v )
            if ((int)$v[0] === $this->current_key['course_id']) $sub = true;
        return $sub;
    }

    public function get_tokens() {
        return $this->tokens;
    }

    public function get_current_key() {
        return $this->current_key;
    }

    private function set_current_key( $key ) {
        global $wpdb; $table = self::$table; $this->token = $key;
        $this->current_key = $wpdb->get_results("SELECT * FROM $table WHERE mu_key = '$key';", ARRAY_A)[0] ?? [];
        foreach ($this->current_key as $k => $v)
            if ($k !== 'mu_key') $this->current_key[$k] = (int) $v;
        return $this;
    }

    private function set_active_user( $active_user = 0 ){
        $this->current_key['active_user'] = $active_user;
        return $this;
    }

    private function set_access_expiration( $exp = 0 ) {
        $this->current_key['date_expires'] = $this->current_key['date_created'] + $exp;
        return $this;
    }

    private function set_activated_time( $time = null ){
        $this->current_key['date_activated'] = $time ?? $this->start_time;
        return $this;
    }

    private function errors() {
        global $wpdb;
        return $wpdb->last_error;
    }
}