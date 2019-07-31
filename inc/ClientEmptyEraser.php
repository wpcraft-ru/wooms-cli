<?php
namespace WooMS\CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Autobot ClientEmptyEraser
 *
 * example command for wp cli:
 * wp wooms_remove_clients --limit=100 --name="Клиент по заказу №68836"
 */
final class ClientEmptyEraser
{
    public static $client_name = '';
    public static $limit = 25;
    public static $log = [];
    public static $cli = false;

    public static function init(){

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            self::$cli = true;
            \WP_CLI::add_command( 'wooms_remove_clients', function($args, $assoc_args){
                if(!empty((int)$assoc_args['limit'])){
                    self::$limit = (int)$assoc_args['limit'];
                }

                if(!empty($assoc_args['name'])){
                    self::$client_name = $assoc_args['name'];
                }

                if(empty(self::$client_name)){
                    \WP_CLI::error('no $client_name');
                    exit;
                }

                self::walker();
            } );
        }
    }

    /**
     * walker
     */
    public static function walker(){
        $url = 'https://online.moysklad.ru/api/remap/1.1/entity/counterparty';

        $url = add_query_arg('limit', self::$limit, $url);
        $url = add_query_arg('filter=name', self::$client_name, $url);

        self::$log['url'] = sprintf('Запрос Клиентов: %s', $url);

        $data = wooms_request($url);

        if(empty($data['rows'])){
            self::$log[] = 'no clients';
            self::stop();
            return;
        }

        $i = 0;
        foreach ($data['rows'] as $item){
            $i++;
            if(!empty($item['salesAmount'])){
                $message = sprintf('client active %s (uuid: %s)', $item['name'], $item['id']);
                \WP_CLI::error( $message, $exit = true);
                continue;
            }

            echo PHP_EOL;
            $res = self::delete_client_by_uuid($item['id']);
            if(!$res){
                continue;
            }

            if(self::$cli){
                $message = sprintf('client removed: %s (uuid: %s)', $item['name'], $item['id']);
                \WP_CLI::log( $message );
            } else {
                print_r($item);
            }

        }

        self::$log[] = 'Количество обработанных записей: ' . $i;

        if(self::$cli){
            self::$log[] = 'iteration finish';
            $message = PHP_EOL . implode(PHP_EOL . '---' . PHP_EOL, self::$log);
            \WP_CLI::success($message);
            self::restart();
        } else {
            self::$log[] = 'walker finish';
            $message = implode(PHP_EOL . '---' . PHP_EOL, self::$log);
            var_dump($message);
        }


    }

    public static function restart(){
        self::$log = [];
        self::walker();
    }

    public static function stop(){
        self::$log[] = 'walker stop';
        $message = PHP_EOL . implode(PHP_EOL . '---' . PHP_EOL, self::$log);
        \WP_CLI::log( $message );

    }

    public static function delete_client_by_uuid($uuid = ''){
        if(empty($uuid)) return false;

        $url = sprintf('https://online.moysklad.ru/api/remap/1.1/entity/counterparty/%s', $uuid);
        wooms_request($url, [], 'DELETE');
        return true;
    }
}

ClientEmptyEraser::init();