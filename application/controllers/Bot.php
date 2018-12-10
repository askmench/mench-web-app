<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bot extends CI_Controller
{

    function __construct()
    {
        parent::__construct();

        //Load our buddies:
        $this->output->enable_profiler(FALSE);
    }


    function profile()
    {
        echo_json($this->Comm_model->fb_graph('GET', '/2375537049154935', array()));
    }

    function ff()
    {
        //Fetch all engagements from intent #6653
        $all_engs = $this->Old_model->cr_children_fetch(array(
            'tr_in_parent_id IN (7723)' => null, //,,
            'tr_status' => 1,
            'in_status >=' => 0,
        ));
        foreach ($all_engs as $c_eng) {
            echo ' ' . $c_eng['in_id'] . ' => 000000000000000000, //' . $c_eng['c_outcome'] . '<br />';
        }
    }


    function sync_menu()
    {

        $res = array();
        array_push($res, $this->Comm_model->fb_graph('POST', '/me/messenger_profile', array(
            'get_started' => array(
                'payload' => 'GET_STARTED',
            ),
            'whitelisted_domains' => array(
                'http://local.mench.co',
                'https://mench.co',
                'https://mench.com',
            ),
        )));

        //Wait until Facebook pro-pagates changes of our whitelisted_domains setting:
        sleep(2);

        array_push($res, $this->Comm_model->fb_graph('POST', '/me/messenger_profile', array(
            'persistent_menu' => array(
                array(
                    'locale' => 'default',
                    'composer_input_disabled' => false,
                    'disabled_surfaces' => array('CUSTOMER_CHAT_PLUGIN'),
                    'call_to_actions' => array(
                        array(
                            'title' => '🚩 Action Plan',
                            'type' => 'web_url',
                            'url' => 'https://mench.com/my/actionplan',
                            'webview_height_ratio' => 'tall',
                            'webview_share_button' => 'hide',
                            'messenger_extensions' => true,
                        ),
                    ),
                ),
            ),
        )));

        echo_json($res);
    }


    function facebook_webhook()
    {

        /*
         *
         * The master function for all Facebook webhook calls
         *
         * */

        //Facebook Webhook Authentication:
        $challenge = (isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : null);
        $verify_token = (isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : null);
        $fb_settings = $this->config->item('fb_settings');

        if ($verify_token == '722bb4e2bac428aa697cc97a605b2c5a') {
            echo $challenge;
        }

        //Fetch input data:
        $json_data = json_decode(file_get_contents('php://input'), true);

        //This is for local testing only:
        //$json_data = objectToArray(json_decode('{"object":"page","entry":[{"id":"381488558920384","time":1505007977668,"messaging":[{"sender":{"id":"1443101719058431"},"recipient":{"id":"381488558920384"},"timestamp":1505007977521,"message":{"mid":"mid.$cAAFa9hmVoehkmryMMVeaXdGIY9x5","seq":19898,"text":"Yes"}}]}]}'));


        //Do some basic checks:
        if (!isset($json_data['object']) || !isset($json_data['entry'])) {
            $this->Db_model->tr_create(array(
                'tr_content' => 'facebook_webhook() Function missing either [object] or [entry] variable.',
                'tr_metadata' => $json_data,
                'tr_en_type_id' => 4246, //Platform Error
            ));
            return false;
        } elseif (!$json_data['object'] == 'page') {
            $this->Db_model->tr_create(array(
                'tr_content' => 'facebook_webhook() Function call object value is not equal to [page], which is what was expected.',
                'tr_metadata' => $json_data,
                'tr_en_type_id' => 4246, //Platform Error
            ));
            return false;
        }


        //Loop through entries:
        foreach ($json_data['entry'] as $entry) {

            //check the page ID:
            if (!isset($entry['id']) || !($entry['id'] == $fb_settings['page_id'])) {
                //This can happen for the older webhook that we offered to other FB pages:
                continue;
            } elseif (!isset($entry['messaging'])) {
                $this->Db_model->tr_create(array(
                    'tr_content' => 'facebook_webhook() call missing messaging Array().',
                    'tr_metadata' => $json_data,
                    'tr_en_type_id' => 4246, //Platform Error
                ));
                continue;
            }

            //loop though the messages:
            foreach ($entry['messaging'] as $im) {

                if (isset($im['read'])) {

                    //TODO Only log IF last engagement was 5 minutes+ ago

                    $en = $this->Comm_model->fb_identify_activate($im['sender']['id']);

                    //This callback will occur when a message a page has sent has been read by the user.
                    $this->Db_model->tr_create(array(
                        'tr_metadata' => $json_data,
                        'tr_en_type_id' => 4278, //Message Read
                        'tr_en_credit_id' => (isset($en['u_id']) ? $en['u_id'] : 0),
                        'tr_timestamp' => echo_mili($im['timestamp']), //The Facebook time

                    ));

                } elseif (isset($im['delivery'])) {

                    //TODO Only log IF last engagement was 5 minutes+ ago

                    $en = $this->Comm_model->fb_identify_activate($im['sender']['id']);

                    //This callback will occur when a message a page has sent has been delivered.
                    $this->Db_model->tr_create(array(
                        'tr_metadata' => $json_data,
                        'tr_en_type_id' => 4279, //Message Delivered
                        'tr_en_credit_id' => (isset($en['u_id']) ? $en['u_id'] : 0),
                        'tr_timestamp' => echo_mili($im['timestamp']), //The Facebook time
                    ));

                } elseif (isset($im['referral']) || isset($im['postback'])) {

                    /*
                     * Simple difference:
                     *
                     * Handle the messaging_postbacks event for new conversations
                     * Handle the messaging_referrals event for existing conversations
                     *
                     * */

                    if (isset($im['postback'])) {

                        //The payload field passed is defined in the above places.
                        $payload = $im['postback']['payload']; //Maybe do something with this later?

                        if (isset($im['postback']['referral']) && count($im['postback']['referral']) > 0) {

                            $referral_array = $im['postback']['referral'];

                        } elseif ($payload == 'GET_STARTED') {

                            //The very first payload, set defaults:
                            $referral_array = array(
                                'ref' => 'ACTIONPLANADD10_' . $this->config->item('in_primary_id'),
                            );

                        } else {
                            //Postback without referral!
                            $referral_array = null;
                        }

                    } elseif (isset($im['referral'])) {

                        $referral_array = $im['referral'];

                    }

                    //Did we have a ref from Messenger?
                    $ref = ($referral_array && isset($referral_array['ref']) && strlen($referral_array['ref']) > 0 ? $referral_array['ref'] : null);

                    $en = $this->Comm_model->fb_identify_activate($im['sender']['id']);

                    /*
                    if($ref){
                        //We have referrer data, see what this is all about!
                        //We expect an integer which is the challenge ID
                        $ref_source = $referral_array['source'];
                        $ref_type = $referral_array['type'];
                        $ad_id = ( isset($referral_array['ad_id']) ? $referral_array['ad_id'] : null ); //Only IF user comes from the Ad

                        //Optional actions that may need to be taken on SOURCE:
                        if(strtoupper($ref_source)=='ADS' && $ad_id){
                            //Ad clicks
                        } elseif(strtoupper($ref_source)=='SHORTLINK'){
                            //Came from m.me short link click
                        } elseif(strtoupper($ref_source)=='MESSENGER_CODE'){
                            //Came from m.me short link click
                        } elseif(strtoupper($ref_source)=='DISCOVER_TAB'){
                            //Came from m.me short link click
                        }
                    }
                    */

                    //Log primary engagement:
                    $this->Db_model->tr_create(array(
                        'tr_en_type_id' => (isset($im['referral']) ? 4267 : 4268), //Messenger Referral/Postback
                        'tr_metadata' => $json_data,
                        'tr_en_credit_id' => (isset($en['u_id']) ? $en['u_id'] : 0),
                        'tr_timestamp' => echo_mili($im['timestamp']), //The Facebook time
                    ));


                    //We might need to respond based on the reference:
                    $this->Comm_model->fb_ref_process($u, $ref);


                } elseif (isset($im['optin'])) {

                    $en = $this->Comm_model->fb_identify_activate($im['sender']['id']);

                    //Note: Never seen this happen yet!
                    //Log transaction:
                    $this->Db_model->tr_create(array(
                        'tr_metadata' => $json_data,
                        'tr_en_type_id' => 4266, //Messenger Optin
                        'tr_en_credit_id' => (isset($en['u_id']) ? $en['u_id'] : 0),
                        'tr_timestamp' => echo_mili($im['timestamp']), //The Facebook time
                    ));

                } elseif (isset($im['message_request']) && $im['message_request'] == 'accept') {

                    //This is when we message them and they accept to chat because they had Archived Messenger or something...
                    $en = $this->Comm_model->fb_identify_activate($im['sender']['id']);

                } elseif (isset($im['message'])) {

                    /*
                     * Triggered for both incoming and outgoing messages on behalf of our team
                     *
                     * */


                    //Is this a non loggable message? If so, this has already been logged by Mench:
                    $metadata = (isset($im['message']['metadata']) ? $im['message']['metadata'] : null); //Send API custom string [metadata field]
                    if ($metadata == 'system_logged') {
                        //This is already logged! No need to take further action!
                        echo_json(array('complete' => 'yes'));
                        return false;
                        exit;
                    }


                    //Set variables:
                    $sent_from_us = (isset($im['message']['is_echo'])); //Indicates the message sent from the page itself
                    $user_id = ($sent_from_us ? $im['recipient']['id'] : $im['sender']['id']);
                    $quick_reply_payload = (isset($im['message']['quick_reply']['payload']) && strlen($im['message']['quick_reply']['payload']) > 0 ? $im['message']['quick_reply']['payload'] : null);
                    $fb_message = (isset($im['message']['text']) ? $im['message']['text'] : null);

                    $en = $this->Comm_model->fb_identify_activate($user_id);

                    $eng_data = array(
                        'tr_en_credit_id' => ($sent_from_us ? 4148 /* Log on behalf of Mench Admins as it was sent via Facebook Inbox UI */ : $en['u_id']),
                        'tr_metadata' => $json_data,
                        'tr_content' => $fb_message,
                        'tr_en_type_id' => ($sent_from_us ? 4280 : 4277), //Message Sent/Received
                        'tr_en_child_id' => ($sent_from_us && isset($en['u_id']) ? $en['u_id'] : 0),
                    );

                    //We only have a timestamp for received messages (not sent ones):
                    if (!$sent_from_us) {
                        $eng_data['tr_timestamp'] = echo_mili($im['timestamp']); //The Facebook time
                    }

                    //It may also have an attachment
                    if (isset($im['message']['attachments'])) {
                        //We have some attachments, lets loops through them:
                        foreach ($im['message']['attachments'] as $att) {

                            if (in_array($att['type'], array('image', 'audio', 'video', 'file'))) {

                                //Indicate that we need to save this file on our servers:
                                $eng_data['tr_status'] = 0;
                                //We do not save instantly as we need to respond to facebook's webhook call ASAP or else FB resend attachment!

                            } elseif ($att['type'] == 'location') {

                                //Message with location attachment
                                //TODO test to make sure this works!
                                $loc_lat = $att['payload']['coordinates']['lat'];
                                $loc_long = $att['payload']['coordinates']['long'];
                                $eng_data['tr_content'] .= (strlen($eng_data['tr_content']) > 0 ? "\n\n" : '') . '/attach location:' . $loc_lat . ',' . $loc_long;

                            } elseif ($att['type'] == 'template') {

                                //Message with template attachment, like a button or something...
                                $template_type = $att['payload']['template_type'];

                            } elseif ($att['type'] == 'fallback') {

                                //A fallback attachment is any attachment not currently recognized or supported by the Message Echo feature.
                                //We can ignore them for now :)

                            } else {
                                //This should really not happen!
                                $this->Db_model->tr_create(array(
                                    'tr_content' => 'facebook_webhook() Received message with unknown attachment type [' . $att['type'] . '].',
                                    'tr_metadata' => $json_data,
                                    'tr_en_type_id' => 4246, //Platform Error
                                    'tr_en_child_id' => $eng_data['tr_en_child_id'],
                                ));
                            }
                        }
                    }

                    //Log incoming engagement:
                    $this->Db_model->tr_create($eng_data);

                    //Process both
                    if ($quick_reply_payload) {
                        $this->Comm_model->fb_ref_process($u, $quick_reply_payload);
                    } elseif (!$sent_from_us) {
                        $this->Comm_model->fb_message_process($u, $fb_message);
                    }

                } else {

                    //This should really not happen!
                    $this->Db_model->tr_create(array(
                        'tr_content' => 'facebook_webhook() received unrecognized webhook call.',
                        'tr_metadata' => $json_data,
                        'tr_en_type_id' => 4246, //Platform Error
                    ));

                }
            }
        }
    }

}