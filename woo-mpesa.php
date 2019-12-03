<?php

/** 
    * Plugin Name: WooCommerce M-PESA Payment (DRC)
    * Plugin URI:  
    * Description: M-PESA (DRC) Payment Gateway for woocommerce by M@estro Jims.
    * Version: 0.0.1
    * Author: M@estro Jims 
    * Author URI: https://web.facebook.com/maestrojims/?ref=bookmarks
    * Licence: GPL2 
    * WC requires at least:  
    * WC tested up to:  
**/ 


// In order to prevent direct access to the plugin

defined('ABSPATH') or die("No access please!");
    /**
     * Vérifiez si WooCommerce est actif
     **/
    if (!in_array ('woocommerce/woocommerce.php', apply_filters ('active_plugins', get_option ('active_plugins')))) {
        die("Erreur : Woocommerce n\'est pas chargé");
    }

    register_activation_hook(__FILE__,'woo_mpesa_install');
    // Chargement plugin
    add_action ('plugins_loaded', 'woo_mpesa_gateway_init');
    add_filter ('woocommerce_payment_gateways', 'add_woo_mpesa_gateway_class');

    function add_woo_mpesa_gateway_class ($method) {
        $method [] = 'Woo_Mobile_Pay';
        return $method;
    }

    function woo_mpesa_install()
    {
        global $wpdb;

        $table_name=$wpdb->prefix.'woo_mpesa_trsx';

      
        $table_schema='CREATE TABLE IF NOT EXISTS '.$table_name.'(
            id INT NOT NULL AUTO_INCREMENT,
            token VARCHAR(255) NOT NULL,
            telephone VARCHAR(255) NOT NULL,
            currency VARCHAR(255) NOT NULL,
            amount VARCHAR(255) NOT NULL,
            trsx_date_time DATETIME DEFAULT NOW() NOT NULL,
            trx_id VARCHAR(255) NOT NULL,
            order_id VARCHAR(255),
            status VARCHAR(50),
            PRIMARY KEY(id)
        )';

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($table_schema);

    }

    //Creation de la classe
    function woo_mpesa_gateway_init(){

        class Woo_Mobile_Pay extends WC_Payment_Gateway {


            /**
             * Woo_Mobile_Pay constructor.
             */
            public function __construct()
            {

                $this->id='mpesardc';
                $this->icon=plugin_dir_url(__FILE__).'assets/img/mpesa_logo.png';
                $this->has_fields=true;
                $this->method_title='Mpesa (RDC)';
                $this->method_description='Accepter les paiements via Mpesa sur votre boutique ';
                $this->init_form_fields();
                $this->init_settings();
                $this->title=$this->get_option('title');
                $this->description=$this->get_option('description');

                // sauvegarde des paramètres
                add_action('woocommerce_update_options_payment_gateways_'. $this->id, array ($this, 'process_admin_options'));

                //init pay
                add_action('woocommerce_init_pay', array($this, 'init_mpesa_pay'));
            }

            protected function logDir(){
                return __DIR__.'/logs';
            }
            /**
             * Initialiser les champs du formulaire de paramètres de passerelle
             */
            public function init_form_fields () {
                $this-> form_fields = array (
                    'enabled' => array (
                        'title' => __ ('Activer / Désactiver', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __ ('Activer le paiement par Mpesa(DRC)', 'woocommerce'),
                        'default' => __('false'),
                    ),
                    'title' => array (
                        'title' => __ ('Titre', 'woocommerce'),
                        'type' => 'text',
                        'description' => __ ('Nom de la passerrelle', 'woocommerce'),
                        'default'=>'Mpesa (DRC)'
                    ),
                    'merchant' => array (
                        'title' => __ ('Marchand', 'woocommerce'),
                        'type' => 'text',
                        'description' => __ ('Nom du Marchand', 'woocommerce'),
                    ),
                    'email' => array (
                        'title' => __ ('Email de notification', 'woocommerce'),
                        'type' => 'email',
                        'description' => __ ('C\'est sur cette email que vous recevrez les statuts des transactions M-Pesa', 'woocommerce'),
                        'default'=>'jimmyvitakasongo@gmail.com'
                    ),
                    'mpesa_username' => array (
                        'title' => __ ('Username', 'woocommerce'),
                        'type' => 'hidden',
                        'description' => __ ('Ppasser à la version pro', 'woocommerce'),

                    ),
                    'mpesa_pass' => array (
                        'title' => __ ('password', 'woocommerce'),
                        'type' => 'hidden',
                        'description' => __ ('Passer à la version pro', 'woocommerce'),
                        "default"=>"thirdpartyc2bw",
                    ),
                    'mpesa_endpoint' => array (
                        'title' => __ ('mpesa_endpoint', 'woocommerce'),
                        'type' => 'hidden',
                        'description' => __ ('Passer à la version pro', 'woocommerce'),
                        'default'=>'https://uatipg.m-pesa.vodacom.cd:8091/insight/SOAPIn'

                    ),
                    'shortcode' => array (
                        'title' => __ ('shortcode', 'woocommerce'),
                        'type' => 'hidden',
                        'description' => __ ('Passer à la version pro', 'woocommerce'),
                        'default'=>'8337',

                    ),
                    'commandId' => array (
                        'title' => __ ('commandid', 'woocommerce'),
                        'type' => 'hidden',
                        'description' => __ ('Passer à la version pro', 'woocommerce'),
                        'default'=>'InitTrans_oneForallC2B'

                    ),
                    'description' => array (
                        'title' => __ ('Description', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __ ('Ceci contrôle la description que l\'utilisateur voit lors du paiement.', 'woocommerce'),
                        'default' => __ ("Payer avec Mpesa (DRC) ", 'woocommerce')
                    )
                );
            }


            public function admin_options() {
                ?>
                <pre><?php _e('Mpesa','woocommerce'); ?></pre>
                <p>Plugin Mpesa by M@estro Jims</p>
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
                <?php
            }

            public function mpesaAuth()
            {
                $data='<?xml version="1.0" encoding="UTF-8"?> <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="http://www.4cgroup.co.za/soapauth" xmlns:gen="http://www.4cgroup.co.za/genericsoap"> <soapenv:Header> <soap:EventID>2500</soap:EventID> </soapenv:Header> <soapenv:Body> <gen:getGenericResult> <Request> <dataItem> <name>Username</name> <type>String</type> <value>'.$this->get_option('mpesa_username').'</value> </dataItem> <dataItem> <name>Password</name> <type>String</type> <value>'.$this->get_option('mpesa_pass').'</value> </dataItem> </Request> </gen:getGenericResult> </soapenv:Body> </soapenv:Envelope>';

                return $this->sendByCurl($this->get_option('mpesa_endpoint'),$data);
            }

            public function makePay($token,$amount,$order_id)
            {
                $data='<?xml version="1.0" encoding="UTF-8"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:soap="http://www.4cgroup.co.za/soapauth" xmlns:gen="http://www.4cgroup.co.za/genericsoap"><soapenv:Header><soap:Token xmlns:soap="http://www.4cgroup.co.za/soapauth">'.$token.'</soap:Token><soap:EventID>80049</soap:EventID></soapenv:Header><soapenv:Body><gen:getGenericResult><Request><dataItem><name>CustomerMSISDN</name><type>String</type><value>'.$_POST['tel'].'</value></dataItem><dataItem><name>ServiceProviderCode</name><type>String</type><value>'.$this->get_option('shortcode').'</value></dataItem><dataItem><name>Currency</name><type>String</type><value>USD</value></dataItem><dataItem><name>Amount</name><type>String</type><value>'.$amount.'</value></dataItem><dataItem><name>Date</name><type>String</type><value>'.date('Ymdims').'</value></dataItem><dataItem><name>ThirdPartyReference</name><type>String</type><value>Gracim-'.$order_id.'-'.uniqid().'</value></dataItem><dataItem><name>CommandId</name><type>String</type><value>'.$this->get_option('commandId').'</value></dataItem><dataItem><name>Language</name><type>String</type><value>FR</value></dataItem><dataItem><name>CallBackChannel</name><type>String</type><value>4</value></dataItem><dataItem><name>CallBackDestination</name><type>String</type><value>'.$this->get_option('email').'</value></dataItem><dataItem><name>Surname</name><type>String</type><value>'.$this->get_option('merchant').'</value></dataItem><dataItem><name>Initials</name><type>String</type><value>Test</value></dataItem></Request></gen:getGenericResult></soapenv:Body></soapenv:Envelope>';
                return $this->sendByCurl($this->get_option('mpesa_endpoint'),$data);
            }
            public function process_payment($order_id)
            {

                $response=$this->mpesaAuth();

                if(!$response){
                    wc_add_notice("Désolé nous n'arrivons pas à joindre le serveur Mpesa, veuillez réessayer ulterieurement",'error');
                }
                else{
                    $order = new WC_Order( $order_id );

                    $response=soapXmlToJson($response);

                    $mpesa_token=$response["ns2getGenericResultResponse"]['SOAPAPIResult']['response']['dataItem']['value'];

                    $payment=soapXmlToJson($this->makePay($mpesa_token,$order->get_total(),$order_id));
                    if($payment["ns2getGenericResultResponse"]['SOAPAPIResult']['response']['dataItem'][1]['value']=='0'){
                        wc_add_notice("Vous allez recevoir une demande de confirmation sur votre téléphone dans quelques sécondes. Vous serez contacter dès nous pourrons confirmer votre paiement");

                        $order->update_status('on-old');
                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url( $order )
                        );

                    }else{
                        var_dump($payment["ns2getGenericResultResponse"]['SOAPAPIResult']['response']['dataItem'][1]);
                        wc_add_notice("Erreur : veuillez réessayer","error");
                    }

                }

            }

            public function payment_fields()
            {
                ?>

                        <label for="tel">Numero vodacom (mpesa)</label>
                        <input id="tel" type="tel" value="2438" name="tel">

                <?php
            }

            public function validate_fields()
            {
               return true;
            }

            public function sendByCurl($url,$data)
            {
                    $r = curl_init();
                    curl_setopt($r, CURLOPT_URL, $url);
/*
                    curl_setopt($r, CURLOPT_SSLKEY, dirname(__FILE__) . "/SSL/key.pem");
                    curl_setopt($r, CURLOPT_CAINFO, dirname(__FILE__) . "/SSL/ca.pem");
                    curl_setopt($r, CURLOPT_SSLCERT, dirname(__FILE__) . "/SSL/cert.pem");
*/

                    curl_setopt($r, CURLOPT_POST, true);
                    curl_setopt($r, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml")); //  multipart/form-data
                    curl_setopt($r, CURLOPT_RETURNTRANSFER, 1); //return results
                    curl_setopt($r,CURLOPT_SSL_VERIFYHOST,FALSE);
                    curl_setopt($r,CURLOPT_SSL_VERIFYPEER,FALSE);
                    curl_setopt($r, CURLOPT_POSTFIELDS, $data);
                    curl_setopt($r, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($r, CURLOPT_CONNECTTIMEOUT, 300);

                    //send and return data to caller
                    $result = curl_exec($r);

                    if(curl_errno($r)>0):
                        $this->setLog(curl_error($r). ' cURL errno = '.curl_errno($r));
                        return false;
                    else:

                        curl_close($r);
                        return $result;
                    endif;


            }

            public function setLog($log)
            {
                if(!is_dir($this->logDir())){
                    mkdir($this->logDir());
                }
                $file=fopen($this->logDir().'/errors.txt','a+');
                fwrite($file,"\r\n".date('Y-m-d H:i:s')." Curl_Error = $log ");
                fclose($file);
            }
        } // End class Wc_Mobile_Pay


    }

function soapXmlToJson($xml){

    $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml);
    $xml = new SimpleXMLElement($response);
    $body = $xml->xpath('//SBody')[0];
    return json_decode(json_encode((array)$body), TRUE);
}
