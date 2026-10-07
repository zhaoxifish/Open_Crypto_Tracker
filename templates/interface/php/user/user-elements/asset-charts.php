<?php
/*
 * Copyright 2014-2026 GPLv3, Open Crypto Tracker by Mike Kilday: Mike@DragonFrugal.com (leave this copyright / attribution intact in ALL forks / copies!)
 */


	
// Have this script not load any code if asset charts are not turned on
if ( $ct['conf']['charts_alerts']['enable_price_charts'] == 'on' ) {

$charted_val = ( $chart_mode == 'pair' ? $alerts_mrkt_parse[2] : $ct['default_bitcoin_primary_currency_pair'] );
		
// Strip non-alphanumeric characters to use in js vars, to isolate logic for each separate chart
$js_key = preg_replace("/-/", "", $alerts_mrkt_parse[0]) . '_' . $charted_val;

$pref_chart_time_period = ( isset($_COOKIE['pref_chart_time_period']) ? $_COOKIE['pref_chart_time_period'] : 'all' );
		
	// Have this script send the UI alert messages, and not load any chart code (to not leave the page endlessly loading) if cache data is not present
	if ( file_exists('cache/charts/spot_price_24hr_volume/light/'.$pref_chart_time_period.'_days/'.$chart_asset.'/'.$alerts_mrkt_parse[0].'_chart_'.$charted_val.'.dat') != 1
	|| $alerts_mrkt_parse[3] != 'chart' && $alerts_mrkt_parse[3] != 'both' ) {
		
		// If we have disabled this chart AFTER adding it at some point earlier (fixes "loading charts" not closing)
		if ( $alerts_mrkt_parse[3] != 'chart' && $alerts_mrkt_parse[3] != 'both' ) {
		$chart_error_notice = ' &nbsp; Chart data is no longer configured for:<br /> &nbsp; ';
		}
		else {
		$chart_error_notice = ' &nbsp; No light chart data built / re-built yet for:<br /> &nbsp; ';
		}
	
	?>
			
			$("#<?=$alerts_mrkt_parse[0]?>_<?=$charted_val?>_chart span.chart_loading").html('<?=$chart_error_notice?><?=$chart_asset?> / <?=strtoupper($alerts_mrkt_parse[2])?> @ <?=$ct['gen']->key_to_name($alerts_mrkt_parse[1])?><?=( $chart_mode != 'pair' ? ' \(' . strtoupper($charted_val) . ' Value\)' : '' )?>');
			
			$("#<?=$alerts_mrkt_parse[0]?>_<?=$charted_val?>_chart span.chart_loading").css({ "background-color": "#9b4b26" });
			
			$("#charts_error").show();
			
			$("#charts_error").html('<div class="btc-chart-empty"><h4>图表数据尚未就绪</h4><p>一个或多个市场尚无可用图表。新安装或刚启用图表时，需要等待后台任务采集行情，并生成对应周期的数据。</p><p>请在 <a href="admin.php#admin_asset_tracking">管理后台 → 资产跟踪 → 价格提醒与图表</a> 中确认该市场已启用图表，并按 <a href="README.txt" target="_blank" rel="noopener">运行说明</a> 启用 cron 或系统定时任务。图表使用实际采集的行情，较长周期会随时间逐步完善。</p><p>若任务运行后仍无数据，请检查市场配置和应用日志，确认行情接口可用、缓存目录可写且磁盘空间充足。调整图表周期后，也需要等待后台重建缓存。</p></div>');
			
			charts_loaded.push("chart_<?=$js_key?>");
			charts_loading_check();
			
	<?php
	}
	else {		
	?>


var light_state_<?=$js_key?> = {
  current: '<?=$pref_chart_time_period?>'
};
 

$("#<?=$alerts_mrkt_parse[0]?>_<?=$charted_val?>_chart span.chart_loading").html(' &nbsp; <img class="ajax_loader_image" src="templates/interface/media/images/auto-preloaded/loader.gif" height="16" alt="" style="vertical-align: middle;" /> &nbsp; Loading <?=$ct['gen']->light_chart_time_period($pref_chart_time_period, 'long')?> chart for:<br /> &nbsp; <?=$chart_asset?> / <?=strtoupper($alerts_mrkt_parse[2])?> @ <?=$ct['gen']->key_to_name($alerts_mrkt_parse[1])?><?=( $chart_mode != 'pair' ? ' \(' . strtoupper($charted_val) . ' Value\)' : '' )?>...');
	
  
zingchart.bind('<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart', 'load', function() {
$("#<?=$alerts_mrkt_parse[0]?>_<?=$charted_val?>_chart span.chart_loading").hide(); // Hide "Loading chart X..." after it loads
});
  

zingchart.TOUCHZOOM = 'pinch'; /* mobile compatibility */

$.get( "ajax.php?type=chart&mode=asset_price&asset_data=<?=$alerts_mrkt_parse[0]?>&charted_val=<?=$chart_mode?>&days=<?=$pref_chart_time_period?>", function( json_data ) {
 

	// Mark chart as loaded after it has rendered
	zingchart.bind('<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart', 'complete', function() {
	$("#<?=$alerts_mrkt_parse[0]?>_<?=$charted_val?>_chart span.chart_loading").hide(); // Hide "Loading chart X..." after it loads
	charts_loaded.push("chart_<?=$js_key?>");
	charts_loading_check();
	});

	zingchart.render({
  	id: '<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart',
  	width: '100%',
  	data: json_data
	});

 
});


zingchart.bind('<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart', 'label_click', function(e){
    
// Set scroll position upon chart link clicks, to avoid page jumping from other zingchart bindings
// when the charts page is set as the start page
store_scroll_position(); 
	
  if(light_state_<?=$js_key?>.current === e.labelid){
    return;
  }
  
  // Reset any user-adjusted zoom
  zingchart.exec('<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart', 'viewall', {
    graphid: 0
  });
  
  var cut = 0;
  switch(e.labelid) {
  	
  	<?php
	foreach ($ct['light_chart_day_intervals'] as $light_chart_days) {
	?>	
	
    case '<?=$light_chart_days?>':
    var days = '<?=$light_chart_days?>';
    break;
    
	<?php
	}
	?>
	
    default: 
      var days = 'all';
    break;
    
  }
  
  
  light_chart_text = light_chart_time_period(days, 'long');	
  
  
  $("#<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart div.chart_reload div.chart_reload_msg").html("Loading " + light_chart_text + " chart for:<br /><?=$chart_asset?> / <?=strtoupper($alerts_mrkt_parse[2])?> @ <?=$ct['gen']->key_to_name($alerts_mrkt_parse[1])?><?=( $chart_mode != 'pair' ? ' \(' . strtoupper($charted_val) . ' Value\)' : '' )?>...");
  
	$("#<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart div.chart_reload").fadeIn(100); // 0.1 seconds
	
  zingchart.bind('<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart', 'complete', function() {
	$( "#<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart div.chart_reload" ).fadeOut(2500); // 2.5 seconds
	});
  
  zingchart.exec('<?=strtolower($alerts_mrkt_parse[0])?>_<?=$charted_val?>_chart', 'load', {
  	dataurl: "ajax.php?type=chart&mode=asset_price&asset_data=<?=$alerts_mrkt_parse[0]?>&charted_val=<?=$chart_mode?>&days=" + days,
    cache: {
        data: true
    }
  });
  
  light_state_<?=$js_key?>.current = e.labelid;
  
});



<?php
	}

}
	
$chart_mode = null; 
 ?>