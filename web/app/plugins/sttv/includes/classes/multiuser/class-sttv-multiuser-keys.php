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

    private static $table_name = 'mu_keys';

    public function __construct( $user_id = 0, $course_id = 0, $autosave = true ) {
        $this->start_time = time();

        if ( is_string( $user_id ) ) {
            $this->set_current_key( $user_id );
            $user_id = 0;
        }
        
        $this->root_user = $user_id;
        $this->course_id = $course_id;
        $this->autosave = $autosave;
        return $this;
    }

    public static function pricing( $stuff = '[]' ){
        $stuff = json_decode( $stuff );
        foreach ( $stuff as $k => $v ){
            $stuff = $k;
        }
        return $stuff;
    }

    public static function getAllKeys() {
        if ( current_user_can( 'manage_options' ) ) {
            global $wpdb;
            $table = $wpdb->prefix.self::$table_name;
            return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        }
    }

    public function keygen( $qty = 0 ) {
        $qty = (int) $qty;

        if ( $qty < self::MIN_KEYS ){
            return null;
        }

        $prefix = array_merge(range('A','Z'),range('a','z'));
        array_unshift($prefix,'\t');
        
        for ($i = 1; $i <= $qty;) {
            $key = sttv_ukey( $prefix[ date( 'n' ) ].$prefix[ date( 'y' ) ].'-', openssl_random_pseudo_bytes( 32 ), true, 32 );
            if ( array_key_exists( $key, $this->keys ) || array_key_exists( $key, $this->created_keys ) ){
                continue;
            }
            
            $this->tokens[] = $key;

            $this->created_keys[$key] = [
                'created' => $this->start_time,
                'activated' => null,
                'expires' => strtotime( '+1 year', $this->start_time ),
                'root_user' => $this->root_user_id,
                'active_user' => 0,
                'course_id' => $this->course_id,
                'valid' => true
            ];

            $i++;
        }

        if ($this->autosave) {
            $this->update($this->created_keys);
        }

        return $this->get_tokens();
    }

    public function activate_key( $user_id = 0, $time = MONTH_IN_SECONDS * 6 ) {
        return $this->set_active_user( $user_id )
            ->set_activated_time()
            ->set_access_expiration( $time )
            ->invalidate_key()
            ->update( $this->current_key )
            ->current_key;
    }

    public function validate_key( $key = '' ) {
        if ( !array_key_exists( $key, $this->keys ) ){
            return false;
        }

        $this->set_current_key( $key );

        if ( $this->current_key[$key]['valid'] && $this->current_key[$key]['expires'] < time() ) {
            $this->invalidate_key( $key );
            $this->update( $this->current_key );
        }

        if ( !$this->current_key[$key]['valid'] ) {
            return false;
        }

        return $this->current_key;
    }

    public function is_subscribed( $active_user = 0, $course_id = 0 ) {
        foreach ( $this->keys as $k => $v ) {
            if ( $v['active_user'] === $active_user && $v['course_id'] == $course_id ) {
                return true;
            }
        }
        return false;
    }

    public function reset_key( $key = '' ) {
        if ( !empty( $key ) ) {
            $this->set_current_key( $key );
        }
        return $this->_reset();
    }

    public function get_tokens() {
        return $this->tokens;
    }

    public function get_current_key() {
        return $this->current_key;
    }

    private function set_current_key( $key ) {
        $this->token = $key;
        $this->current_key = [
            $key => $this->keys[$key]
        ];
        return $this;
    }

    private function invalidate_key(){
        $this->current_key[$this->token]['valid'] = false;
        return $this;
    }

    private function _reset() {
        if ( is_null( $this->current_key[ $this->token ][ 'activated' ] ) ) {
            return null;
        }
        unset( $this->current_key[ $this->token ][ 'course_exp' ] );
        $this->current_key[ $this->token ] = array_merge( $this->current_key[ $this->token ], [
            'activated' => null,
            'valid' => true,
            'active_user' => 0
        ] );

        return $this->update( $this->current_key )
            ->get_current_key();
    }

    private function set_active_user( $active_user = 0 ){
        $this->current_key[$this->token]['active_user'] = $active_user;
        return $this;
    }

    private function set_access_expiration( $exp ) {
        $this->current_key[$this->token]['course_exp'] = time() + $exp;
        return $this;
    }

    private function set_activated_time(){
        $this->current_key[$this->token]['activated'] = time();
        return $this;
    }

    private function add() {
        file_put_contents( MU_FILE_PATH, json_encode( $this->created_keys ), FILE_APPEND | LOCK_EX );
        return $this;
    }

    private function update( $update = [] ) {
        file_put_contents( MU_FILE_PATH, json_encode( array_merge( $this->keys, $update ) ), LOCK_EX );
        return $this;
    }

    private function delete( $key = '' ){
        if ( 0 === $key || 'all' === $key ){
            $this->keys = [];
        } else {
            unset( $this->keys[$key] );
        }
        return $this->update();
    }
}