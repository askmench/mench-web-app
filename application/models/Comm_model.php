<?php if ( !defined('BASEPATH')) exit('No direct script access allowed');

class Comm_model extends CI_Model {
		
	function __construct() {
		parent::__construct();
	}
	
    function fb_graph($action,$url,$payload=array()){

	    //Do some initial checks
	    if(!in_array($action, array('GET','POST','DELETE'))){

	        //Only 4 valid types of $action
            return array(
                'status' => 0,
                'message' => '$action ['.$action.'] is invalid',
            );

        }


        //Start building GET URL:
        if(array_key_exists('access_token',$payload)){

            //This this access token:
            $access_token_payload = array(
                'access_token' => $payload['access_token'],
            );
            //Remove it just in case:
            unset($payload['access_token']);

        } else {
            //Apply the Page Access Token:
            $fb_settings = $this->config->item('fb_settings');
            $access_token_payload = array(
                'access_token' => $fb_settings['mench_access_token']
            );
        }

        if($action=='GET' && count($payload)>0){
            //Add $payload to GET variables:
            $access_token_payload = array_merge($access_token_payload,$payload);
            $payload = array();
        }

        $url = 'https://graph.facebook.com/v2.6'.$url;
        $counter = 0;
        foreach($access_token_payload as $key=>$val){
            $url = $url.($counter==0?'?':'&').$key.'='.$val;
            $counter++;
        }

        //Make the graph call:
        $ch = curl_init($url);

        //Base setting:
        $ch_setting = array(
            CURLOPT_CUSTOMREQUEST => $action,
            CURLOPT_RETURNTRANSFER => TRUE,
        );

        if(count($payload)>0){
            $ch_setting[CURLOPT_HTTPHEADER] = array('Content-Type: application/json; charset=utf-8');
            $ch_setting[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        //Apply settings:
        curl_setopt_array($ch, $ch_setting);

        //Process results and produce e_json
        $result = objectToArray(json_decode(curl_exec($ch)));
        $e_json = array(
            'action' => $action,
            'payload' => $payload,
            'url' => $url,
            'result' => $result,
        );

        //Did we have any issues?
        if(!$result){

            //Failed to fetch this profile:
            $error_message = 'Comm_model->fb_graph() failed to '.$action.' '.$url;
            $this->Db_model->e_create(array(
                'e_text_value' => $error_message,
                'e_inbound_c_id' => 8, //Platform Error
                'e_json' => $e_json,
            ));

            //There was an issue accessing this on FB
            return array(
                'status' => 0,
                'message' => $error_message,
                'e_json' => $e_json,
            );

        } else {

            //All seems good, return:
            return array(
                'status' => 1,
                'message' => 'Success',
                'e_json' => $e_json,
            );

        }
    }

    function fb_identify_activate($fp_psid, $fb_ref=null, $fb_message_received=null){

	    /*
	     *
	     * Function will detect the entity (user) ID of all inbound messages
	     *
	     */

        if($fp_psid<1){
            //Ooops, this is not good:
            $this->Db_model->e_create(array(
                'e_text_value' => 'fb_identify_activate() got called without $fp_psid variable',
                'e_inbound_c_id' => 8, //Platform Error
            ));
            return false;
        }

        //Try finding user references... Is this psid already registered?
        //We either have the user in DB or we'll register them now:
        $fb_message_received = strtolower($fb_message_received);
        $lets_command_guide = 'You can give me a command by starting a sentence with "Lets", for example: [Lets get hired as a developer], [Lets book more interviews] or [Lets build a great resume]';
        $fetch_us = $this->Db_model->u_fetch(array(
            'u_cache__fp_psid' => $fp_psid,
        ), array('u__ws'));

        if(count($fetch_us)>0){

            //User found:
            $u = $fetch_us[0];

        } else {

            //This is a new user that needs to be registered!
            //Call facebook messenger API and get user profile
            $graph_fetch = $this->Comm_model->fb_graph('GET', '/'.$fp_psid, array());

            if(!$graph_fetch['status'] || !isset($graph_fetch['e_json']['result']['first_name']) || strlen($graph_fetch['e_json']['result']['first_name'])<1){

                $this->Db_model->e_create(array(
                    'e_text_value' => 'fb_identify_activate() failed to fetch user profile from Facebook Graph',
                    'e_json' => array(
                        'fp_psid' => $fp_psid,
                        'fb_ref' => $fb_ref,
                        'graph_fetch' => $graph_fetch,
                    ),
                    'e_inbound_c_id' => 8, //Platform Error
                ));

                //We cannot create this user:
                return false;
            }

            //We're cool!
            $fb_profile = $graph_fetch['e_json']['result'];

            //Split locale into language and country
            $locale = explode('_',$fb_profile['locale'],2);

            //Create user
            $u = $this->Db_model->u_create(array(
                'u_full_name' 		=> $fb_profile['first_name'].' '.$fb_profile['last_name'],
                'u_timezone' 		=> $fb_profile['timezone'],
                'u_gender'		 	=> strtolower(substr($fb_profile['gender'],0,1)),
                'u_language' 		=> $locale[0],
                'u_country_code' 	=> $locale[1],
                'u_cache__fp_psid'  => $fp_psid,
            ));

            //No subscriptions at this point:
            $u['u__ws'] = array();

            //Update Algolia:
            $this->Db_model->algolia_sync('u', $u['u_id']);

            //Save picture locally:
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $u['u_id'],
                'e_text_value' => $fb_profile['profile_pic'], //Image to be saved
                'e_status' => 0, //Pending upload
                'e_inbound_c_id' => 7001, //Cover Photo Save
            ));

            //Log new user engagement:
            $this->Db_model->e_create(array(
                'e_inbound_u_id' => $u['u_id'],
                'e_inbound_c_id' => 27, //User Joined
            ));
        }


        //By now we have a user, which we should return if we don't have a message or a ref code:
        if(!$fb_ref && !$fb_message_received){
            return $u;
        }


        $c_target_outcome = null;
        if(substr_count($fb_message_received, 'lets ')>0){
            $c_target_outcome =  one_two_explode('lets ', '', $fb_message_received);
        } elseif(substr_count($fb_message_received, 'let’s ')>0){
            $c_target_outcome =  one_two_explode('let’s ', '', $fb_message_received);
        } elseif(substr_count($fb_message_received, 'let\'s ')>0){
            $c_target_outcome =  one_two_explode('let\'s ', '', $fb_message_received);
        }

        //Did we have a command?
        if($c_target_outcome){

            //TODO migrate this to NLP framework for more accurate results:
            $search_index = load_php_algolia('alg_intents');
            $res = $search_index->search($c_target_outcome, [
                'hitsPerPage' => 6
            ]);

            if($res['nbHits']>0){

                //Show options for them to subscribe to:
                $quick_replies = array();
                $i_message = 'I found the following intent'.echo__s($res['nbHits']).':';
                foreach ($res['hits'] as $count=>$hit){
                    //Translate hours back:
                    $hit['c__tree_max_hours'] = number_format(($hit['c__tree_max_mins']/60), 3);
                    $hit['c__tree_min_hours'] = number_format(($hit['c__tree_min_mins']/60), 3);
                    $i_message .= "\n\n".($count+1).'/ '.$hit['c_outcome'].' in '.strip_tags(echo_hour_range($hit));
                    array_push($quick_replies , array(
                        'content_type' => 'text',
                        'title' => ($count+1).'/',
                        'payload' => 'SUBSCRIBE20_'.$hit['c_id'],
                    ));
                }

                //Give them a none option:
                $i_message .= "\n\n".($count+2).'/ None of the above';
                array_push($quick_replies , array(
                    'content_type' => 'text',
                    'title' => ($count+2).'/',
                    'payload' => 'SUBSCRIBE10_0',
                ));

                //return what we found to the student to decide:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => $i_message,
                        'quick_replies' => $quick_replies,
                    ),
                ));

            } else {

                /*
                //Create new intent in the suggestion bucket:
                $this->Db_model->c_new(7431, $c_target_outcome, 0, 2, $u['u_id']);

                //Also log engagement for points purposes later down the road...
                $this->Db_model->e_create(array(
                    'e_text_value' => 'User suggested ['.$c_target_outcome.'] to be added as an entity.',
                    'e_inbound_u_id' => $u['u_id'],
                    'e_inbound_c_id' => 7431, //Suggest new intent
                ));
                */

                //Respond to user:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => 'I am currently not trained to ['.$c_target_outcome.'], but I have logged this for my human team mates to look into. I will let you know as soon as I am trained on this. Is there anything else I can help you with right now?',
                    ),
                ));

            }

        } elseif(trim($fb_message_received)=='unsubscribe'){

            //User has requested to be removed. Let's see what they have:
            if(count($u['u__ws'])>0){

                $quick_replies = array();
                $i_message = 'Which of the following intentions would you like to unsubscribe from?';
                $increment = 1;

                foreach($u['u__ws'] as $counter=>$w){
                    //Construct unsubscribe confirmation body:
                    $i_message .= "\n\n".'/'.($counter+$increment).' '.$w['c_outcome'].' ['.echo_diff_time($w['w_timestamp']).']';
                    array_push( $quick_replies , array(
                        'content_type' => 'text',
                        'title' => '/'.($counter+$increment),
                        'payload' => 'UNSUBSCRIBE_'.$w['w_id'],
                    ));
                }


                if(count($u['u__ws'])>1){
                    //We have more than one option, give an unsubscribe all option:
                    $increment++;
                    $i_message .= "\n\n".'/'.($counter+$increment).' Unsubscribe from All';
                    array_push( $quick_replies , array(
                        'content_type' => 'text',
                        'title' => '/'.($counter+$increment),
                        'payload' => 'UNSUBSCRIBE_ALL',
                    ));
                }

                //Alwyas give none option:
                $increment++;
                $i_message .= "\n\n".'/'.($counter+$increment).' No, Stay Friends';
                array_push( $quick_replies , array(
                    'content_type' => 'text',
                    'title' => '/'.($counter+$increment),
                    'payload' => 'UNSUBSCRIBE_CANCEL',
                ));

                //Send out message and let them confirm:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => $i_message,
                        'quick_replies' =>$quick_replies,
                    ),
                ));

            } elseif($fetch_us[0]['u_status']==-1) {

                //User is already unsubscribed, let them know:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => 'You are already unsubscribed from Mench and will no longer receive any communication from us. To subscribe again, '.$lets_command_guide,
                    ),
                ));

            } else {

                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => 'Got it, just to confirm, you want me to stop all future communications with you?',
                        'quick_replies' => array(
                            array(
                                'content_type' => 'text',
                                'title' => 'Yes, Unsubscribe',
                                'payload' => 'UNSUBSCRIBE_ALL',
                            ),
                            array(
                                'content_type' => 'text',
                                'title' => 'No, Stay Friends',
                                'payload' => 'UNSUBSCRIBE_CANCEL',
                            ),
                        ),
                    ),
                ));

            }

        } elseif(substr_count($fb_ref, 'UNSUBSCRIBE_')==1){

            $unsub_value = one_two_explode('UNSUBSCRIBE_', '', $fb_ref);

            if($unsub_value=='CANCEL'){

                //User changed their mind, confirm:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => 'Awesome, would be happy to stay friends and help you accomplish your career goals. '.$lets_command_guide,
                    ),
                ));

            } elseif($unsub_value=='ALL'){

                //User wants completely out...

                //Unsubscribe from all.
                $this->db->query("UPDATE v5_subscriptions SET w_status=-1 WHERE w_status>=0 AND w_outbound_u_id=".$fetch_us[0]['u_id']);
                $total_unsubscribed = $this->db->affected_rows();

                //Update User table status:
                $this->Db_model->u_update( $fetch_us[0]['u_id'] , array(
                    'u_status' => -1, //Unsubscribed
                ));

                //Log engagement:
                $this->Db_model->e_create(array(
                    'e_inbound_u_id' => $fetch_us[0]['u_id'], //Initiated by PA
                    'e_outbound_u_id' => $fetch_us[0]['u_id'],
                    'e_text_value' => 'Student unsubscribed from all '.$total_unsubscribed.' subscriptions',
                    'e_inbound_c_id' => 7452, //User Unsubscribed
                ));

                //Let them know:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => ''.( $total_unsubscribed>0 ? 'Confirmed, I have unsubscribed you from '.$total_unsubscribed.' intent'.echo__s($total_unsubscribed).'.' : 'Confirmed!').' This is the final message you will receive from me unless you send me a message at any time. Take care of your self and I hope to talk to you soon.',
                    ),
                ));

            } elseif(intval($unsub_value)>0){

                //User wants to remove a specific subscription, validate it:
                $subscriptions = $this->Db_model->w_fetch(array(
                    'w_id' => intval($unsub_value),
                    'w_status >=' => 0,
                ), array('w_c_id'));

                //All good?
                if(count($subscriptions)==1){

                    //Update status for this single subscription:
                    $this->db->query("UPDATE v5_subscriptions SET w_status=-1 WHERE w_id=".intval($unsub_value));

                    //Log engagement:
                    $this->Db_model->e_create(array(
                        'e_inbound_u_id' => $fetch_us[0]['u_id'],
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'e_outbound_c_id' => $subscriptions[0]['w_c_id'],
                        'e_text_value' => 'Student unsubscribed from their intention to '.$subscriptions[0]['c_outcome'],
                        'e_inbound_c_id' => 7452, //User Unsubscribed
                        'e_w_id' => intval($unsub_value),
                    ));

                    //Show success message to user:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'i_message' => 'I have successfully unsubscribed you from your intention to '.$subscriptions[0]['c_outcome'].'. Say "Unsubscribe" again if you wish to stop all future communications. '.$lets_command_guide,
                        ),
                    ));

                } else {

                    //let them know we had error:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'i_message' => 'Unable to process your request as I could not locate your subscription. Please try again.',
                        ),
                    ));

                    //Log error engagement:
                    $this->Db_model->e_create(array(
                        'e_inbound_u_id' => $fetch_us[0]['u_id'],
                        'e_text_value' => 'Student attempted to unsubscribe but failed to do so',
                        'e_inbound_c_id' => 8, //System error
                        'e_w_id' => intval($unsub_value),
                    ));

                }
            }

        } elseif($fb_message_received && !$fb_ref){

            //We have a regular inbound message from the user:
            if(count($fetch_us[0]['u__ws'])==0){

                //Ask if they are interested to join the primary intent:
                //Amazing, move on to next step:
                $fb_ref = 'SUBSCRIBE10_6623';

            } else {

                /*
                //We do not accept inbound messages unless they are subscribed to a premium membership, let them know this:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => 'I am unable to respond to your messages unless you enroll in a coaching plan that will connect you to an industry expert for live chats, assignment review and more',
                    ),
                ));

                //Offer coaching plan introduction:
                $fb_ref = 'SUBSCRIBE10_7440';

                */

            }

        }


        if(substr_count($fb_ref, 'SUBSCRIBE10_')==1) {

            //Validate this intent:
            $c_id = intval(one_two_explode('SUBSCRIBE10_', '', $fb_ref));

            if($c_id==0){

                //They rejected the offer... Acknowledge and give response:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'i_message' => 'Ok, so what is your biggest career-related challenge? '.$lets_command_guide,
                    ),
                ));

            } else {

                $fetch_cs = $this->Db_model->c_fetch(array(
                    'c_id' => $c_id,
                ));

                //Any issues?
                if(count($fetch_cs)<1) {

                    //Ooops we could not find that C:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'i_message' => 'I was unable to locate intent #'.$c_id.' ['.$fb_ref.']',
                        ),
                    ));

                } elseif($fetch_cs[0]['c_status']<1) {

                    //Ooops C is no longer active:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'i_message' => 'I was unable to subscribe you to '.$fetch_cs[0]['c_outcome'].' as its no longer active',
                        ),
                    ));

                } else {

                    //Confirm if they are interested for this intention:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'e_outbound_c_id' => $fetch_cs[0]['c_id'],
                            'i_message' => 'Are you interested to '.$fetch_cs[0]['c_outcome'].'?',
                            'quick_replies' => array(
                                array(
                                    'content_type' => 'text',
                                    'title' => 'Yes, Learn More',
                                    'payload' => 'SUBSCRIBE20_'.$fetch_cs[0]['c_id'],
                                ),
                                array(
                                    'content_type' => 'text',
                                    'title' => 'No',
                                    'payload' => 'SUBSCRIBE10_0',
                                ),
                            ),
                        ),
                    ));

                }
            }

        } elseif(substr_count($fb_ref, 'SUBSCRIBE20_')==1){

            //Initiating an intent Subscription:
            $w_c_id = intval(one_two_explode('SUBSCRIBE20_', '', $fb_ref));
            $fetch_cs = $this->Db_model->c_fetch(array(
                'c_id' => $w_c_id,
                'c_status >=' => 1,
            ));
            if (count($fetch_cs)==1) {

                //Intent seems good...
                //See if this intent belong to any of these subscriptions:
                $subscription_intents = $this->Db_model->k_fetch(array(
                    'w_outbound_u_id' => $fetch_us[0]['u_id'], //All subscriptions belonging to this user
                    'w_status >=' => 0, //With an active status
                    '(cr_inbound_c_id='.$w_c_id.' OR cr_outbound_c_id='.$w_c_id.')' => null,
                ), array('cr','w_c'));

                if(count($subscription_intents)>0){

                    //Let the user know that this is a duplicate:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'e_outbound_c_id' => $fetch_cs[0]['c_id'],
                            'i_message' => ( $subscription_intents[0]['c_id']==$w_c_id ? 'You have already subscribed to '.$fetch_cs[0]['c_outcome'].'. We have been working on it together since '.echo_time($subscription_intents[0]['w_timestamp'], 2).'. /open_actionplan' : 'Your subscription to '.$subscription_intents[0]['c_outcome'].' already covers the intention to '.$fetch_cs[0]['c_outcome'].', so I will not create a duplicate subscription. /open_actionplan' ),
                        ),
                    ));

                } else {

                    //Fetch all the messages for this intent:
                    $tree = $this->Db_model->c_recursive_fetch($w_c_id,1,0);

                    //Show messages for this intent:
                    $messages = $this->Db_model->i_fetch(array(
                        'i_outbound_c_id' => $w_c_id,
                        'i_status >=' => 0, //Published in any form
                    ));
                    foreach($messages as $i){
                        $this->Comm_model->send_message(array(
                            array_merge($i, array(
                                'e_inbound_u_id' => 2738, //Initiated by PA
                                'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            )),
                        ));
                    }

                    //Send message for final confirmation:
                    $this->Comm_model->send_message(array(
                        array(
                            'e_inbound_u_id' => 2738, //Initiated by PA
                            'e_outbound_u_id' => $fetch_us[0]['u_id'],
                            'e_outbound_c_id' => $w_c_id,
                            'i_message' => 'To '.$fetch_cs[0]['c_outcome'].' will take about '.echo_hour_range($fetch_cs[0]).' to complete. Ready to get started?',
                            'quick_replies' => array(
                                array(
                                    'content_type' => 'text',
                                    'title' => 'Yes, Subscribe',
                                    'payload' => 'SUBSCRIBE99_'.$w_c_id,
                                ),
                                array(
                                    'content_type' => 'text',
                                    'title' => 'No',
                                    'payload' => 'SUBSCRIBE10_0',
                                ),
                                //TODO Maybe Show a "learn more" if Drip messages available?
                            ),
                        ),
                    ));

                }
            }

        } elseif(substr_count($fb_ref, 'SUBSCRIBE99_')==1){

            $w_c_id = intval(one_two_explode('SUBSCRIBE99_', '', $fb_ref));
            $fetch_cs = $this->Db_model->c_fetch(array(
                'c_id' => $w_c_id,
                'c_status >=' => 1,
            ));

            if (count($fetch_cs)==1) {

                //Create a new subscription (Which will also cache action plan):
                $w = $this->Db_model->w_create(array(
                    'w_c_id' => $w_c_id,
                    'w_outbound_u_id' => $fetch_us[0]['u_id'],
                ));

                //Confirm with them that we're now ready:
                $this->Comm_model->send_message(array(
                    array(
                        'e_inbound_u_id' => 2738, //Initiated by PA
                        'e_outbound_u_id' => $fetch_us[0]['u_id'],
                        'e_outbound_c_id' => $w_c_id,
                        'i_message' => 'You are now subscribed 🙌 I will be handing everything else from here to ensure you '.$fetch_cs[0]['c_outcome'].' /open_actionplan',
                    ),
                ));

            }
        }


        //Return user Object:
        return $u;

    }



    function send_message($messages,$force_email=false,$intent_title_subject=false){

        if(count($messages)<1){
            return array(
                'status' => 0,
                'message' => 'No messages set',
            );
        }

        $failed_count = 0;
        $email_to_send = array();
        $e_json = array(
            'messages' => array(),
            'email' => array(),
        );

        foreach($messages as $message){

            //Make sure we have the necessary fields:
            if(!isset($message['e_outbound_u_id'])){
                //Log error:
                $this->Db_model->e_create(array(
                    'e_json' => $message,
                    'e_inbound_c_id' => 8, //Platform error
                    'e_text_value' => 'send_message() failed to send message as it was missing e_outbound_u_id',
                ));
                continue;
            }

            //TODO Implement simple caching to remember $dispatch_fp_psid && $u IF some details remain the same
            if(1){

                //Fetch user communication preferences:
                $users = array();
                if(!$force_email && isset($message['e_w_id']) && $message['e_w_id']>0){
                    //TODO Fetch enrollment to class...
                }

                if(count($users)<1){
                    //Fetch user profile via their account:
                    $users = $this->Db_model->u_fetch(array(
                        'u_id' => $message['e_outbound_u_id'],
                    ));
                }

                if(count($users)<1){

                    //Log error:
                    $failed_count++;
                    $this->Db_model->e_create(array(
                        'e_outbound_u_id' => $message['e_outbound_u_id'],
                        'e_json' => $message,
                        'e_inbound_c_id' => 8, //Platform error
                        'e_text_value' => 'send_message() failed to fetch user details message as it was missing core variables',
                    ));
                    continue;

                } else {

                    //Determine communication method:
                    $dispatch_fp_psid = 0;
                    $u = array();

                    if(!$force_email && $users[0]['u_cache__fp_psid']>0){
                        //We fetched an enrollment with an active Messenger connection:
                        $dispatch_fp_psid = $users[0]['u_cache__fp_psid'];
                        $u = $users[0];
                    } elseif(strlen($users[0]['u_email'])>0 && filter_var($users[0]['u_email'], FILTER_VALIDATE_EMAIL)){
                        //User has not activated Messenger but has email:
                        $u = $users[0];
                    } else {

                        //This should technically not happen!
                        //Log error:
                        $failed_count++;
                        $this->Db_model->e_create(array(
                            'e_outbound_u_id' => $message['e_outbound_u_id'],
                            'e_json' => $message,
                            'e_inbound_c_id' => 8, //Platform error
                            'e_text_value' => 'send_message() detected user without an active email/Messenger with $force_email=['.($force_email?'1':'0').']',
                        ));
                        continue;

                    }
                }
            }



            //Send using email or Messenger?
            if(!$force_email && $dispatch_fp_psid){

                $w_notification_types = echo_status('w_notification_type');

                //Prepare Payload:
                $payload = array(
                    'recipient' => array(
                        'id' => $dispatch_fp_psid,
                    ),
                    'message' => echo_i($message, $u['u_full_name'],true),
                    'messaging_type' => 'NON_PROMOTIONAL_SUBSCRIPTION', //https://developers.facebook.com/docs/messenger-platform/send-messages#messaging_types
                    // TODO fetch from w_notification_type & translate 'notification_type' => $w_notification_types[$w['w_notification_type']]['s_fb_key'],
                );

                //Messenger:
                $process = $this->Comm_model->fb_graph('POST','/me/messages', $payload);

                //Log Outbound Message Engagement:
                $this->Db_model->e_create(array(
                    'e_inbound_u_id' => ( isset($message['e_inbound_u_id']) ? $message['e_inbound_u_id'] : 0 ),
                    'e_outbound_u_id' => ( isset($message['e_outbound_u_id']) ? $message['e_outbound_u_id'] : 0 ),
                    'e_text_value' => $message['i_message'],
                    'e_json' => array(
                        'input_message' => $message,
                        'input_force_email' => ( $force_email ? 1 : 0 ),
                        'input_intent_title_subject' => ( $intent_title_subject ? 1 : 0 ),
                        'payload' => $payload,
                        'results' => $process,
                    ),
                    'e_inbound_c_id' => 7, //Outbound message
                    'e_i_id'  => ( isset($message['i_id'])      ? $message['i_id']    :0), //The message that is being dripped
                    'e_outbound_c_id'  => ( isset($message['i_outbound_c_id']) ? $message['i_outbound_c_id'] : 0),
                ));

                if(!$process['status']){
                    $failed_count++;
                }

                array_push( $e_json['messages'] , $process );

            } else {

                //This is an email request, combine the emails per user:
                if(!isset($email_to_send[$u['u_id']])){


                    $subject_line = 'New Message from Mench';

                    /*
                    if($intent_title_subject && isset($message['i_outbound_c_id']) && $message['i_outbound_c_id']>0){
                        $intents = $this->Db_model->c_fetch(array(
                            'c.c_id' => $message['i_outbound_c_id'],
                        ));
                        if(count($intents)>0){
                            $subject_line = $intents[0]['c_outcome'];
                        }
                    }
                    */

                    $email_variables = array(
                        'u_email' => $u['u_email'],
                        'subject_line' => $subject_line,
                        'html_message' => echo_i($message, $u['u_full_name'],false),
                    );


                    $e_var_create = array(
                        'e_var_create' => array(
                            'e_inbound_u_id' => ( isset($message['e_inbound_u_id']) ? $message['e_inbound_u_id'] : 0 ), //If set...
                            'e_outbound_u_id' => $u['u_id'],
                            'e_text_value' => $email_variables['subject_line'],
                            'e_json' => $email_variables,
                            'e_inbound_c_id' => 28, //Email message sent
                            'e_outbound_c_id'  => ( isset($message['i_outbound_c_id']) ? $message['i_outbound_c_id'] : 0 ),
                        ),
                    );

                    $email_to_send[$u['u_id']] = array_merge($email_variables,$e_var_create);

                } else {
                    //Append message to this user:
                    $email_to_send[$u['u_id']]['html_message'] .= '<div style="padding-top:12px;">'.echo_i($message, $u['u_full_name'],false).'</div>';
                }

            }
        }


        //Do we have to send message?
        if(count($email_to_send)>0){
            //Yes, go through these emails and send them:
            foreach($email_to_send as $email){
                $process = $this->Comm_model->send_email(array($email['u_email']), $email['subject_line'], $email['html_message'], $email['e_var_create'], 'support@mench.com' /*Hack! To be replaced with ceo email*/);

                array_push( $e_json['email'] , $process );
            }
        }



        if($failed_count>0){

            return array(
                'status' => 0,
                'message' => 'Failed to send '.$failed_count.'/'.count($messages).' message'.echo__s(count($messages)).'.',
                'e_json' => $e_json,
            );

        } else {

            return array(
                'status' => 1,
                'message' => 'Successfully sent '.count($messages).' message'.echo__s(count($messages)),
                'e_json' => $e_json,
            );

        }
    }

    function foundation_message($message,$force_email=false){

        //Validate key components that are required:
        $error_message = null;
        if(count($message)<1){
            $error_message = 'Missing $message';
        } elseif(!isset($message['e_outbound_c_id']) || $message['e_outbound_c_id']<1){
            $error_message = 'Missing e_outbound_c_id';
        } elseif(!isset($message['e_outbound_u_id']) || $message['e_outbound_u_id']<1) {
            $error_message = 'Missing e_outbound_u_id';
        }

        if(!$error_message){

            $message['depth'] = 0; //Override this for now and only focus on dispatching Steps at 1 level
            $message['e_inbound_u_id'] = 0; //System, prevents any signatures from being appended...

            //Fetch Bootcamp/Class if needed:
            $bs = array();
            $b_data = null;
            $class = null;


            //Fetch intent and its messages with an appropriate depth
            $fetch_depth = ( $message['depth']>1 ? $message['depth'] : 1 ); //Used to be something different! Just changed it quickly, not sure if this makes sense
            $tree = $this->Db_model->c_fetch(array(
                'c.c_id' => $message['e_outbound_c_id'],
            ), $fetch_depth, array('c__messages')); //Supports up to 2 levels deep for now...


            //Check to see if we have any other errors:
            if(!isset($tree[0])){
                $error_message = 'Invalid Intent ID ['.$message['e_outbound_c_id'].']';
            } elseif($message['depth']<0 || $message['depth']>1){
                $error_message = 'Invalid depth ['.$message['depth'].']';
            }
        }

        //Did we catch any errors?
        if($error_message){
            //Log error:
            $this->Db_model->e_create(array(
                'e_text_value' => 'foundation_message() error: '.$error_message,
                'e_inbound_c_id' => 8, //Platform Error
                'e_json' => $message,
                'e_outbound_u_id' => $message['e_outbound_u_id'],
                'e_outbound_c_id' => $message['e_outbound_c_id'],
                'e_inbound_u_id' => $message['e_inbound_u_id'],
            ));

            //Return error:
            return array(
                'status' => 0,
                'message' => $error_message,
            );
        }


        //Let's start adding-up the instant messages:
        $instant_messages = array();


        //Append main object messages:
        if(isset($tree[0]['c__messages']) && count($tree[0]['c__messages'])>0){
            //We have messages for the very first level!
            foreach($tree[0]['c__messages'] as $key=>$i){
                if($i['i_status']==1){
                    //Add message to instant stream:
                    array_push($instant_messages , array_merge($message, $i));
                }
            }
        }

        //Anything to be sent instantly?
        if(count($instant_messages)<1){
            //Nothing to be sent
            return array(
                'status' => 0,
                'message' => 'No messages to be sent',
            );
        }

        //All good, attempt to Dispatch all messages, their engagements have already been logged:
        return $this->Comm_model->send_message($instant_messages,$force_email,true);
    }

    function send_email($to_array,$subject,$html_message,$e_var_create=array(),$reply_to=null){

        if(is_dev()){
            return true;
        }

        //Loadup amazon SES:
        require_once( 'application/libraries/aws/aws-autoloader.php' );
        $this->CLIENT = new Aws\Ses\SesClient([
            'version' 	    => 'latest',
            'region'  	    => 'us-west-2',
            'credentials'   => $this->config->item('aws_credentials'),
        ]);

        if(!$reply_to){
            //Set default:
            $reply_to = 'support@mench.com';
        }

        //Log engagement once:
        if(count($e_var_create)>0){
            $this->Db_model->e_create($e_var_create);
        }

        return $this->CLIENT->sendEmail(array(
            // Source is required
            'Source' => 'support@mench.com',
            // Destination is required
            'Destination' => array(
                'ToAddresses' => $to_array,
                'CcAddresses' => array(),
                'BccAddresses' => array(),
            ),
            // Message is required
            'Message' => array(
                // Subject is required
                'Subject' => array(
                    // Data is required
                    'Data' => $subject,
                    'Charset' => 'UTF-8',
                ),
                // Body is required
                'Body' => array(
                    'Text' => array(
                        // Data is required
                        'Data' => strip_tags($html_message),
                        'Charset' => 'UTF-8',
                    ),
                    'Html' => array(
                        // Data is required
                        'Data' => $html_message,
                        'Charset' => 'UTF-8',
                    ),
                ),
            ),
            'ReplyToAddresses' => array($reply_to),
            'ReturnPath' => 'support@mench.com',
        ));

    }

}