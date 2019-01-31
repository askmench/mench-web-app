<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Keep a cache of certain parts of the Intent tree for faster processing
 * So we don't have to make DB calls to figure them out every time!
 * This is the cron function that creates this: fn___matrix_cache()
 * See here for all entities cached: https://mench.com/entities/4527
 * use-case format: $this->config->item('en_all_3000')
 *
 * ATTENTION: Also search for "en_ids_" and "en_all_" when trying to manage these throughout the code base
 *
 */



//All entity links:
$config['en_ids_4592'] = array(4230, 4255, 4256, 4257, 4258, 4259, 4260, 4261, 4318, 4319);
$config['en_all_4592'] = array(
    4230 => array(
        'm_icon' => '<i class="fal fa-link"></i>',
        'm_name' => 'Raw',
        'm_desc' => '',
    ),
    4255 => array(
        'm_icon' => '<i class="fal fa-align-left"></i>',
        'm_name' => 'Text',
        'm_desc' => '',
    ),
    4256 => array(
        'm_icon' => '<i class="fal fa-browser"></i>',
        'm_name' => 'URL',
        'm_desc' => '',
    ),
    4257 => array(
        'm_icon' => '<i class="fal fa-play-circle"></i>',
        'm_name' => 'Embed',
        'm_desc' => '',
    ),
    4258 => array(
        'm_icon' => '<i class="fal fa-video"></i>',
        'm_name' => 'Video',
        'm_desc' => '',
    ),
    4259 => array(
        'm_icon' => '<i class="fal fa-volume-up"></i>',
        'm_name' => 'Audio',
        'm_desc' => '',
    ),
    4260 => array(
        'm_icon' => '<i class="fal fa-image"></i>',
        'm_name' => 'Image',
        'm_desc' => '',
    ),
    4261 => array(
        'm_icon' => '<i class="fal fa-file-pdf"></i>',
        'm_name' => 'File',
        'm_desc' => '',
    ),
    4318 => array(
        'm_icon' => '<i class="fal fa-clock"></i>',
        'm_name' => 'Time',
        'm_desc' => '',
    ),
    4319 => array(
        'm_icon' => '<i class="fal fa-sort-numeric-down"></i>',
        'm_name' => 'Integer',
        'm_desc' => '',
    ),
);

//Intent Completion Requirements:
$config['en_ids_4331'] = array(4255, 4256, 4258, 4259, 4260, 4261);
$config['en_all_4331'] = array(
    4255 => array(
        'm_icon' => '<i class="fal fa-align-left"></i>',
        'm_name' => 'Text',
        'm_desc' => '',
    ),
    4256 => array(
        'm_icon' => '<i class="fal fa-browser"></i>',
        'm_name' => 'URL',
        'm_desc' => '',
    ),
    4258 => array(
        'm_icon' => '<i class="fal fa-video"></i>',
        'm_name' => 'Video',
        'm_desc' => '',
    ),
    4259 => array(
        'm_icon' => '<i class="fal fa-volume-up"></i>',
        'm_name' => 'Audio',
        'm_desc' => '',
    ),
    4260 => array(
        'm_icon' => '<i class="fal fa-image"></i>',
        'm_name' => 'Image',
        'm_desc' => '',
    ),
    4261 => array(
        'm_icon' => '<i class="fal fa-file-pdf"></i>',
        'm_name' => 'File',
        'm_desc' => '',
    ),
);

//Mench Database Objects:
$config['en_ids_4534'] = array(4341, 4535, 4536);
$config['en_all_4534'] = array(
    4341 => array(
        'm_icon' => '<i class="fas fa-atlas"></i>',
        'm_name' => 'Ledger Transactions',
        'm_desc' => '',
    ),
    4535 => array(
        'm_icon' => '<i class="fas fa-hashtag"></i>',
        'm_name' => 'Intents',
        'm_desc' => '',
    ),
    4536 => array(
        'm_icon' => '<i class="fas fa-at"></i>',
        'm_name' => 'Entities',
        'm_desc' => '',
    ),
);

//Mench Notification Levels:
$config['en_ids_4454'] = array(4455, 4456, 4457, 4458);
$config['en_all_4454'] = array(
    4455 => array(
        'm_icon' => '<i class="fas fa-minus-circle"></i>',
        'm_name' => 'Unsubscribed from Mench',
        'm_desc' => 'User was connected but requested to be unsubscribed, so we can no longer reach-out to them',
    ),
    4456 => array(
        'm_icon' => '<i class="fas fa-bell"></i>',
        'm_name' => 'Receive Regular Notifications',
        'm_desc' => 'User is connected and will be notified by sound & vibration for new Mench messages',
    ),
    4457 => array(
        'm_icon' => '<i class="fal fa-bell"></i>',
        'm_name' => 'Receive Silent Push Notifications',
        'm_desc' => 'User is connected and will be notified by on-screen notification only for new Mench messages',
    ),
    4458 => array(
        'm_icon' => '<i class="fas fa-bell-slash"></i>',
        'm_name' => 'Do Not Receive Push Notifications',
        'm_desc' => 'User is connected but will not be notified for new Mench messages except the red icon indicator on the Messenger app which would indicate the total number of new messages they have',
    ),
);

//Intent Messages:
$config['en_ids_4485'] = array(4231, 4232, 4233, 4234);
$config['en_all_4485'] = array(
    4231 => array(
        'm_icon' => '<i class="fal fa-bolt"></i>',
        'm_name' => 'On-Start',
        'm_desc' => 'Mench dispatches these messages, in order, when the Master reaches the intent that this message is assigned to. Miners write or upload media to create these messages, and their goal is/should-be to give an introduction of the intention, why its important and the latest overview of how Mench will empower the Master to accomplish the intent. On-start messaged are listed on the intent landing pages e.g. https://mench.com/6903 while also being dispatched when a Master is considering to add a new intent to their Action Plan. These on-start messages give them an overview of what to expect with this intent.',
    ),
    4232 => array(
        'm_icon' => '<i class="fal fa-comment-lines"></i>',
        'm_name' => 'Learn More',
        'm_desc' => 'Authored by Miners and ordered, [Learn More] messages offer Masters a Quick Reply options to get more perspectives on the intention with an additional message batch. If Masters choose to move on without learning more, Mench will communicate the message batch at a later time to deliver the extra perspective on the intention. This is known as "dripping content" that helps re-enforce their learnings and act as a effective reminder of the best-practice, and perhaps a a new twist on how to execute towards it. Learn-More messages will always be delivered, the Master chooses the timing of it.',
    ),
    4233 => array(
        'm_icon' => '<i class="fal fa-calendar-check"></i>',
        'm_name' => 'On-Complete',
        'm_desc' => 'Authored by Miners, these messages are dispatched in-order as a batch of knowledge as soon as the Intent is marked as complete by the Master. On-complete messages can re-iterate key insights to help Masters retain their learnings.',
    ),
    4234 => array(
        'm_icon' => '<i class="fal fa-random"></i>',
        'm_name' => 'Rotating',
        'm_desc' => 'Triggered in various spots of the code base that powers the logic of Mench personal assistant. Search for the compose_messages() function which is part of the Comm Model.',
    ),
);

//Intent Links:
$config['en_ids_4486'] = array(4228, 4229);
$config['en_all_4486'] = array(
    4228 => array(
        'm_icon' => '<i class="fal fa-clipboard-check"></i>',
        'm_name' => 'Pre-Assessment',
        'm_desc' => 'Intent link published and added to user Action Plans up-front',
    ),
    4229 => array(
        'm_icon' => '<i class="fal fa-question-circle fa-spin"></i>',
        'm_name' => 'Post-Assessment',
        'm_desc' => 'Intent added to Action Plans after parent intent is complete AND the user\'s % score falls within the defined min/max range',
    ),
);

//Intent Settings:
$config['en_ids_4487'] = array(4331);
$config['en_all_4487'] = array(
    4331 => array(
        'm_icon' => '<i class="fal fa-shield-check"></i>',
        'm_name' => 'Intent Completion Requirements',
        'm_desc' => 'If applied as the parent of a child intent, would limit the type of responses users can submit for that intent when marking it as complete. Multiple links will enable multiple response types to be accepted, which the user will be informed by Mench.',
    ),
);

//URL-based entity links:
$config['en_ids_4537'] = array(4256, 4257, 4258, 4259, 4260, 4261);
$config['en_all_4537'] = array(
    4256 => array(
        'm_icon' => '<i class="fal fa-browser"></i>',
        'm_name' => 'URL',
        'm_desc' => 'Link note contains a generic URL only.',
    ),
    4257 => array(
        'm_icon' => '<i class="fal fa-play-circle"></i>',
        'm_name' => 'Embed',
        'm_desc' => 'Link note contain a recognizable URL that offers an embed widget for a more engaging play-back experience.',
    ),
    4258 => array(
        'm_icon' => '<i class="fal fa-video"></i>',
        'm_name' => 'Video',
        'm_desc' => 'Link notes contain a URL to a raw video file.',
    ),
    4259 => array(
        'm_icon' => '<i class="fal fa-volume-up"></i>',
        'm_name' => 'Audio',
        'm_desc' => 'Link notes contain a URL to a raw audio file.',
    ),
    4260 => array(
        'm_icon' => '<i class="fal fa-image"></i>',
        'm_name' => 'Image',
        'm_desc' => 'Link notes contain a URL to a raw image file.',
    ),
    4261 => array(
        'm_icon' => '<i class="fal fa-file-pdf"></i>',
        'm_name' => 'File',
        'm_desc' => 'Link notes contain a URL to a raw file.',
    ),
);

//Sources:
$config['en_ids_3000'] = array(2997, 2998, 2999, 3005, 3147, 3192, 4446);
$config['en_all_3000'] = array(
    2997 => array(
        'm_icon' => '<i class="fas fa-newspaper"></i>',
        'm_name' => 'Articles',
        'm_desc' => '',
    ),
    2998 => array(
        'm_icon' => '<i class="fab fa-youtube"></i>',
        'm_name' => 'Videos',
        'm_desc' => '',
    ),
    2999 => array(
        'm_icon' => '<i class="fas fa-microphone"></i>',
        'm_name' => 'Podcasts',
        'm_desc' => '',
    ),
    3005 => array(
        'm_icon' => '<i class="fas fa-book"></i>',
        'm_name' => 'Books',
        'm_desc' => '',
    ),
    3147 => array(
        'm_icon' => '<i class="fas fa-presentation"></i>',
        'm_name' => 'Online Courses',
        'm_desc' => '',
    ),
    3192 => array(
        'm_icon' => '<i class="fas fa-wrench"></i>',
        'm_name' => 'Tools',
        'm_desc' => 'Websites, guides, software or any other tool that could be used to streamline progress.',
    ),
    4446 => array(
        'm_icon' => '<i class="fas fa-tachometer"></i>',
        'm_name' => 'Assessments',
        'm_desc' => '',
    ),
);