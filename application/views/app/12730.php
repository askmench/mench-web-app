<?php

/*
$images = 0;
$fontawesome = 0;
$emoji = 0;
$none = 0;

foreach($this->E_model->fetch(array()) as $e){

    if(substr_count($e['e__cover'],'<img ') && substr_count($e['e__cover'],'src=')){
        $images++;
        $type = 'IMAGE: ';
        $new_cover = one_two_explode('src="','"',$e['e__cover']);
    } elseif(substr_count($e['e__cover'],'fa-') && substr_count($e['e__cover'],'<i ')){
        $fontawesome++;
        $type = 'FONTAWESOME: ';
        $new_cover = one_two_explode('class="','"',$e['e__cover']);
    } elseif(strlen($e['e__cover'])){
        $emoji++;
        $type = 'EMOJI: ';
        $new_cover = $e['e__cover'];
    } else {
        $none++;
        $type = 'NONE: ';
        $new_cover = null;
    }

    echo $type.$e['e__cover'].' ('.$new_cover.') @'.$e['e__id'].'<br />';

    //$this->E_model->update($e['e__id'], array('e__cover' => $new_cover));
}

echo '<br /><br />Images ('.$images.') Fontawesome ('.$fontawesome.') Emoji ('.$emoji.') None ('.$none.')<br />';
*/

//UI to compose a test message:
echo '<form method="GET" action="">';

echo '<div class="mini-header">Search String:</div>';
echo '<input type="text" class="form-control white-border border maxout" name="search_for" value="'.@$_GET['search_for'].'"><br />';



$search_for_set = (isset($_GET['search_for']) && strlen($_GET['search_for'])>0);
$replace_with_set = ((isset($_GET['replace_with']) && strlen($_GET['replace_with'])>0) || (isset($_GET['append_text']) && strlen($_GET['append_text'])>0));
$replace_with_confirmed = false;

if($search_for_set){

    $matching_results = $this->E_model->fetch(array(
        'e__type IN (' . join(',', $this->config->item('n___7358')) . ')' => null, //ACTIVE
        'LOWER(e__title) LIKE \'%'.strtolower($_GET['search_for']).'%\'' => null,
    ));

    //List the matching search:
    echo '<div>'.count($matching_results).' Sources Found</div>';
    if(count($matching_results) < 1){

        $replace_with_set = false;
        unset($_GET['confirm_statement']);
        unset($_GET['replace_with']);

    } else {

        $confirmation_keyword = 'Replace '.count($matching_results);
        $replace_with_confirmed = (isset($_GET['confirm_statement']) && strtolower($_GET['confirm_statement'])==strtolower($confirmation_keyword));

        echo '<div class="list-group">';
        foreach($matching_results as $count=>$en){

            if($replace_with_set){
                //Do replacement:
                $append_text = @$_GET['append_text'];
                $new_outcome = str_replace(strtolower($_GET['search_for']),strtolower($_GET['replace_with']),$en['e__title']).$append_text;

                if($replace_with_confirmed){
                    //Update idea:
                    $res = $this->E_model->update($en['e__id'], array(
                        'e__title' => $new_outcome,
                    ), true, $member_e['e__id']);
                }
            }

            echo view_e(12730, $en, null,  true);
        }
        echo '</div>';

    }

}


if($search_for_set && count($matching_results) > 0){
    //now give option to replace with:
    echo '<div class="mini-header">Replace With:</div>';
    echo '<input type="text" class="form-control white-border border maxout" name="replace_with" value="'.@$_GET['replace_with'].'"><br />';

    //now give option to replace with:
    echo '<div class="mini-header">Append Text:</div>';
    echo '<input type="text" class="form-control white-border border maxout" name="append_text" value="'.@$_GET['append_text'].'"><br />';
}

if($replace_with_set){
    //now give option to replace with:
    echo '<div class="mini-header">Confirm Replacement by Typing "'.$confirmation_keyword.'":</div>';
    echo '<input type="text" class="form-control white-border border maxout" name="confirm_statement" value="'. @$_GET['confirm_statement'] .'"><br />';
}


echo '<input type="submit" class="btn btn-default" value="GO">';
echo '</form>';
