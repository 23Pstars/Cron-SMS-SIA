<?php
/**
 * LRsoft Corp.
 * http://lrsoft.co.id
 *
 * Author: Zaf
 * Date: 12/8/15
 * Time: 8:04 PM
 */

header('Content-Type: application/json');

class Cron_SMS_SIA {

    /**
     * CHANGE ME
     */
    const _SMS_API_SIA = 'http://sia.unram.ac.id/api/{...}/{...}/{...}';

    /**
     * gammu DB
     *
     * CHANGE ME
     */
    const _db = 'gammu';
    const _host = 'localhost';
    const _user = 'user';
    const _pass = 'pass';

    const _table_outbox = 'outbox';
    const _table_user_outbox = 'user_outbox';

    var $db_conn = null;
    var $db_select = null;

    function __construct() {

        if( !$this->db_conn = mysql_connect( self::_host, self::_user, self::_pass ) )
            die( 'Ndeq ne bau konek kadu user : ' . self::_user );

        if( !$this->db_select = mysql_select_db( self::_db, $this->db_conn ) )
            die( 'Ndeq ne bau konek jok DB : ' . self::_db );

    }

    function fetch( $args ) {

        $URL = self::_SMS_API_SIA . '?' . implode( '&', array_map(
                function( $k, $d ) { return $k . '=' . $d; }, array_keys( $args ), $args
            ) );

        return json_decode( file_get_contents( $URL ), true );

    }

    function inject( $SMS_item ) {

        $outbox_status = mysql_query(
            'INSERT INTO `' . self::_table_outbox . '`'
            . ' (`DestinationNumber`, `TextDecoded`, `CreatorID`, `SenderID`)'
            . ' VALUES ("'
            . $SMS_item[ 'number' ] . '", "'
            . $SMS_item[ 'messages' ] . '", "'
            . $SMS_item[ 'phoneid' ] . '", "'
            . $SMS_item[ 'phoneid' ] . '")'
        );

        $user_outbox_status = !$outbox_status ? false : mysql_query(
            'INSERT INTO `' . self::_table_user_outbox . '` (`id_outbox`, `id_user`) VALUES (' . mysql_insert_id() . ', 1)'
        );

        return $outbox_status && $user_outbox_status;
    }

    function push( $ids ) {
        return json_decode(
            file_get_contents(
                self::_SMS_API_SIA . '/SetDelivered?ids=' . implode( ':', $ids )
            )
        );
    }

    function _init() {

        $status = array();

        /**
         * @rules
         * - khusus hari ini
         * - hanya status yang belum terkirim
         * - limitasi harian dari provider telah di-handle oleh sistem SIA
         */
        $SMS_lists = $this->fetch( array(
            'date'      => date( 'Y-m-d' ),
            'status'    => 0,
            'n'         => -1
        ) );

        /**
         * inject tiap item SMS
         */
        foreach( $SMS_lists as $SMS )
            $status[ $SMS[ 'id' ] ] = $this->inject( $SMS );

        /**
         * update kembali ID item SMS pada sistem SIA sesuai dengan yang telah diinject di SMS Gateway
         *
         * @rules
         * - jika ada ID yang tidak sukses di-inject, jangan push ke sistem SIA
         */
        $response = $this->push(
            array_keys(
                array_filter( $status )
            )
        );

        /**
         * untuk keperluan debugging
         */
        echo json_encode( $response );

    }

}

$SMS = new Cron_SMS_SIA();

$SMS->_init();
