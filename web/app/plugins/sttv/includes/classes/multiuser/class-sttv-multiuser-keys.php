<?php

namespace STTV\Multiuser;

defined( 'ABSPATH' ) || exit;

class Keys {

    const MIN_KEYS = 3;

    private $keys;

    private $created_keys = [];

    private $tokens = [];

    private $token = '';

    private $current_key;

    private $start_time;

    private $root_user;

    private $course_id;

    private $autosave;

    private static $table = 'sttvapp_mu_keys';

    public function __construct( $user_id = 0, $course_id = 0, $autosave = true ) {
        $this->start_time = time();

        if ( is_string( $user_id ) ) {
            $this->set_current_key( $user_id );
            $user_id = 0;
        }
        
        $this->root_user = $user_id;
        $this->course_id = $course_id;
        return $this;
    }

    public static function getAllKeys() {
        if ( current_user_can( 'manage_options' ) ) {
            global $wpdb;
            $table = self::$table;
            return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        }
    }

    public function get_keys() {
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
            $key = sttv_ukey( $prefix[ date( 'n' ) ].$prefix[ date( 'y' ) ].'-', openssl_random_pseudo_bytes( 32 ), true, 32 );

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

    public function activate_key( $user_id = 0, $time = MONTH_IN_SECONDS * 6 ) {
        return $this->set_active_user( $user_id )
            ->set_activated_time()
            ->set_access_expiration( $time )
            ->update()
            ->get_current_key();
    }

    public function validate_key( $key = '' ) {
        $this->set_current_key( $key );
        if ( empty( $this->current_key ) || !$this->current_key ) return null;

        if ( $this->current_key['date_expires'] < time() ) {
            $this->invalidate_key()->update();
            return 'expired';
        }

        return $this->current_key;
    }

    public function is_subscribed( $active_user = 0, $course_id = 0 ) {
        global $wpdb;
        $table = self::$table;
        $course_id = $course_id ?: $this->course_id;
        return !!$wpdb->get_results("SELECT * FROM $table WHERE active_user = $active_user AND course_id = $course_id;",ARRAY_A);
    }

    public function reset_key( $key = '' ) {
        if ( !empty( $key ) ) return $this->set_current_key( $key )->_reset();
    }

    public function get_tokens() {
        return $this->tokens;
    }

    public function get_current_key() {
        return $this->current_key;
    }

    private function invalidate_key() {
        $this->current_key['date_expires'] = 0;
        return $this;
    }

    private function set_current_key( $key ) {
        global $wpdb;
        $this->token = $key;
        $table = self::$table;
        $this->current_key = $wpdb->get_results("SELECT * FROM $table WHERE mu_key = '$key';", ARRAY_A)[0] ?? [];
        return $this;
    }

    private function _reset() {
        if ( !$this->current_key[ 'date_activated' ] ) return;

        return $this->set_active_user()
            ->set_access_expiration(YEAR_IN_SECONDS)
            ->set_activated_time(0)
            ->update()
            ->get_current_key();
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

    private function update() {
        global $wpdb;
        $wpdb->update(self::$table,$this->current_key,['mu_key'=>$this->token]);
        return $this;
    }

    private function delete( $key ){
        global $wpdb;
        return $wpdb->delete(self::$table,['mu_key'=>$key]);
    }
}