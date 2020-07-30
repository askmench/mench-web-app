<?php

echo '<table class="table table-sm table-striped stats-table mini-stats-table">';

echo '<tr class="panel-title down-border">';
echo '<td style="text-align: left;">Transaction Type</td>';
echo '<td style="text-align: left;">Coins</td>';
echo '</tr>';


//Count them all:
$e___12140 = $this->config->item('e___12140');

$full_coins = $this->X_model->fetch(array(
    'x__type IN (' . join(',', $this->config->item('n___12141')) . ')' => null, //Full
    'x__status IN (' . join(',', $this->config->item('n___7359')) . ')' => null, //PUBLIC
), array(), 0, 0, array(), 'COUNT(x__id) as total_x');
echo '<tr class="panel-title down-border" style="font-weight: bold;">';
echo '<td style="text-align: left;" class="montserrat doupper">'.$e___12140[12141]['m_icon'].' '.$e___12140[12141]['m_title'].'</td>';
echo '<td style="text-align: left;">'.number_format($full_coins[0]['total_x'], 0).'</td>';
echo '</tr>';


//Add some empty space:
echo '<tr class="panel-title down-border"><td style="text-align: left;" colspan="4">&nbsp;</td></tr>';

//Show each transaction type:
foreach($this->X_model->fetch(array(
    'x__type IN (' . join(',', $this->config->item('n___12141')) . ')' => null, //Full
    'x__status IN (' . join(',', $this->config->item('n___7359')) . ')' => null, //PUBLIC
), array('x__type'), 0, 0, array('total_x' => 'DESC'), 'COUNT(x__id) as total_x, e__title, e__icon, e__id, x__type', 'e__id, e__title, e__icon, x__type') as $x) {

    //Determine which weight group this belongs to:
    $direction = filter_cache_group($x['e__id'], 12467);

    echo '<tr class="panel-title down-border">';
    echo '<td style="text-align: left;"><span class="icon-block">'.$x['e__icon'].'</span><a href="/@'.$x['e__id'].'" class="montserrat doupper">'.$x['e__title'].'</a></td>';
    echo '<td style="text-align: left;"><span class="icon-block">'.$direction['m_icon'].'</span>'.number_format($x['total_x'], 0).'</td>';
    echo '</tr>';

}

echo '</table>';
