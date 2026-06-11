<?php
/**
 * Plugin Name: Estrellas Newton
 * Description: Registro diario de estrellas para docentes y funcionarios (React + REST API + MySQL).
 * Version: 1.0.0
 * Author: Newton
 * Text Domain: estrellas-nw
 */

defined('ABSPATH') || exit;

define('ESTRELLAS_NW_VERSION', '1.0.0');
define('ESTRELLAS_NW_PATH', plugin_dir_path(__FILE__));
define('ESTRELLAS_NW_URL', plugin_dir_url(__FILE__));

require_once ESTRELLAS_NW_PATH . 'includes/class-database.php';
require_once ESTRELLAS_NW_PATH . 'includes/class-rest-api.php';

register_activation_hook(__FILE__, function () {
	Estrellas_NW_Database::install();
});

add_action('rest_api_init', array('Estrellas_NW_Rest_Api', 'register'));

/**
 * Shortcode: [estrellas_nw]
 * Insertá este shortcode en una página de WordPress (solo usuarios logueados pueden usar la API).
 */
add_shortcode('estrellas_nw', function () {
	$base = trailingslashit(ESTRELLAS_NW_URL) . 'public/';
	$css_file = ESTRELLAS_NW_PATH . 'public/assets/app.css';
	$js_file  = ESTRELLAS_NW_PATH . 'public/assets/app.js';
	$css_ver  = file_exists($css_file)
		? md5_file($css_file) . '-' . (string) filemtime($css_file)
		: ESTRELLAS_NW_VERSION;
	$js_ver   = file_exists($js_file)
		? md5_file($js_file) . '-' . (string) filemtime($js_file)
		: ESTRELLAS_NW_VERSION;
	wp_enqueue_style(
		'estrellas-nw-app',
		$base . 'assets/app.css',
		array(),
		$css_ver
	);
	wp_enqueue_script(
		'estrellas-nw-app',
		$base . 'assets/app.js',
		array(),
		$js_ver,
		true
	);
	wp_localize_script(
		'estrellas-nw-app',
		'EstrellasNW',
		array(
			'restBase' => esc_url_raw(rest_url('estrellas-nw/v1')),
			'nonce'    => wp_create_nonce('wp_rest'),
		)
	);
	return '<div id="estrellas-nw-root" class="estrellas-nw-root"></div>';
});

/**
 * Shortcode: [estrellas_nw_podio]
 * Vista pública estilo podio para una página dedicada del ranking.
 */
add_shortcode('estrellas_nw_podio', function () {
	$handle = 'estrellas-nw-podio';

	wp_register_style($handle, false, array(), ESTRELLAS_NW_VERSION);
	wp_enqueue_style($handle);

	$css = "
		.estrellas-podio-wrap{
			--bg-1:#070b2a;
			--bg-2:#111848;
			--text:#eaf0ff;
			--muted:#aab3da;
			--gold:#ffcf5a;
			--silver:#dce7ff;
			--bronze:#ffb06a;
			--violet:#8f6dff;
			background: radial-gradient(circle at 50% 0%, #1d2a68 0%, var(--bg-1) 45%, #05071f 100%);
			border-radius:22px;
			padding:28px 20px;
			color:var(--text);
			font-family: 'Segoe UI', Tahoma, sans-serif;
			box-shadow:0 16px 45px rgba(5,10,45,.55);
		}
		.estrellas-podio-title{ text-align:center; margin:0 0 4px; color:#ffffff; letter-spacing:.6px; font-size:30px; font-weight:800; text-transform:uppercase; }
		.estrellas-podio-subtitle{ text-align:center; margin:0 0 24px; color:var(--muted); font-size:15px; }
		.estrellas-podio-filters{
			display:flex;
			justify-content:center;
			align-items:center;
			gap:10px;
			margin:0 0 18px;
			flex-wrap:wrap;
		}
		.estrellas-podio-filters label{
			font-size:13px;
			color:#c9d5ff;
			font-weight:700;
			text-transform:uppercase;
			letter-spacing:.4px;
		}
		.estrellas-podio-filters select{
			background:#0f1a56;
			color:#fff;
			border:1px solid rgba(154,174,252,.45);
			border-radius:10px;
			padding:8px 12px;
			min-width:210px;
			outline:none;
		}
		.estrellas-podio-loading,.estrellas-podio-empty{ color:var(--muted); text-align:center; padding:12px 0; }
		.estrellas-podio-top3{
			display:flex;
			align-items:flex-end;
			justify-content:center;
			gap:18px;
			margin:8px auto 28px;
			flex-wrap:wrap;
		}
		.estrellas-podio-card{
			width:min(30vw,230px);
			min-width:180px;
			max-width:230px;
			text-align:center;
		}
		.estrellas-podio-star{
			width:148px;
			height:148px;
			margin:0 auto 12px;
			clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
			display:grid;
			place-items:center;
			box-shadow:0 0 18px rgba(255,255,255,.22), 0 0 42px rgba(255,206,100,.28);
		}
		.estrellas-podio-star.p1{ background:linear-gradient(145deg,#ffd667,#d9981c);}
		.estrellas-podio-star.p2{ background:linear-gradient(145deg,#f6fbff,#9eb7de);}
		.estrellas-podio-star.p3{ background:linear-gradient(145deg,#ffd0a0,#c9742c);}
		.estrellas-podio-avatar{
			width:82px;
			height:82px;
			border-radius:999px;
			display:grid;
			place-items:center;
			background:rgba(8,12,40,.62);
			color:#fff;
			font-weight:800;
			font-size:28px;
			border:3px solid rgba(255,255,255,.72);
		}
		.estrellas-podio-name{
			margin:0 0 4px;
			font-size:22px;
			font-weight:700;
			color:#fff;
		}
		.estrellas-podio-points{
			margin:0 0 8px;
			font-weight:700;
			font-size:21px;
		}
		.estrellas-podio-base{
			border-radius:10px 10px 0 0;
			display:grid;
			place-items:center;
			font-weight:900;
			color:#0a1037;
			box-shadow: inset 0 1px 0 rgba(255,255,255,.45);
		}
		.estrellas-podio-base.p1{height:120px;background:linear-gradient(180deg,#f2c65e,#aa7314);font-size:52px;}
		.estrellas-podio-base.p2{height:88px;background:linear-gradient(180deg,#e3ecff,#8ca4ce);font-size:46px;}
		.estrellas-podio-base.p3{height:74px;background:linear-gradient(180deg,#ffc78f,#bd6828);font-size:44px;}
		.estrellas-podio-others{
			display:grid;
			grid-template-columns:repeat(3,minmax(150px,1fr));
			gap:14px;
			margin:0 auto 26px;
			max-width:760px;
		}
		.estrellas-podio-other{
			background:rgba(17,26,84,.55);
			border:1px solid rgba(157,177,255,.24);
			border-radius:14px;
			padding:12px;
			text-align:center;
		}
		.estrellas-podio-badge{
			display:inline-block;
			padding:5px 10px;
			border-radius:999px;
			background:rgba(143,109,255,.2);
			border:1px solid rgba(143,109,255,.5);
			font-weight:700;
			margin-bottom:8px;
		}
		.estrellas-podio-table-wrap{
			background:rgba(11,18,68,.68);
			border:1px solid rgba(154,174,252,.24);
			border-radius:16px;
			padding:10px;
			overflow:auto;
		}
		.estrellas-podio-table{
			width:100%;
			border-collapse:collapse;
			min-width:580px;
		}
		.estrellas-podio-table th,
		.estrellas-podio-table td{
			padding:10px 12px;
			border-bottom:1px solid rgba(168,184,246,.16);
			font-size:14px;
		}
		.estrellas-podio-table th{
			text-align:left;
			text-transform:uppercase;
			letter-spacing:.5px;
			color:#b9c5f4;
			font-size:12px;
		}
		.estrellas-podio-table tr:last-child td{ border-bottom:none; }
		.estrellas-podio-table td:nth-child(1){ width:70px; font-weight:800; color:#f4f7ff; }
		.estrellas-podio-table td:nth-child(3){ font-weight:700; color:#ffe083; }
		@media (max-width:860px){
			.estrellas-podio-title{font-size:24px}
			.estrellas-podio-star{width:130px;height:130px}
			.estrellas-podio-avatar{width:72px;height:72px;font-size:24px}
		}
		@media (max-width:620px){
			.estrellas-podio-others{grid-template-columns:1fr}
			.estrellas-podio-card{min-width:150px}
		}
	";
	wp_add_inline_style($handle, $css);

	wp_register_script($handle, '', array(), ESTRELLAS_NW_VERSION, true);
	wp_enqueue_script($handle);
	wp_localize_script(
		$handle,
		'EstrellasNWPodio',
		array(
			'restRanking' => esc_url_raw(rest_url('estrellas-nw/v1/public/stats/ranking')),
			'nonce'       => wp_create_nonce('wp_rest'),
		)
	);

	$js = "
	(function(){
		var STAR_TYPE_OPTIONS = [
			{ value: '', label: 'General (todas las estrellas)' },
			{ value: 'FUNNY', label: 'Funny' },
			{ value: 'SMARTY', label: 'Smarty' },
			{ value: 'EARLY', label: 'Early' },
			{ value: 'TEACHE', label: 'Teache' },
			{ value: 'BUDDY', label: 'Buddy' },
			{ value: 'BIRTHDAY', label: 'Birthday' }
		];
		function escHtml(str){
			return String(str || '').replace(/[&<>\"']/g, function(c){
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',\"'\":'&#039;'})[c] || c;
			});
		}
		function fmtPoints(n){
			return Number(n || 0).toLocaleString('es-ES') + ' pts';
		}
		function initials(name){
			var parts = String(name || '').trim().split(/\\s+/).filter(Boolean);
			if(!parts.length){ return '??'; }
			if(parts.length === 1){ return parts[0].slice(0,2).toUpperCase(); }
			return (parts[0][0] + parts[1][0]).toUpperCase();
		}
		function podiumCard(item, rank){
			if(!item){
				return '<div class=\"estrellas-podio-card\"></div>';
			}
			var cls = 'p' + rank;
			return ''
				+ '<div class=\"estrellas-podio-card\">'
				+   '<div class=\"estrellas-podio-star ' + cls + '\"><div class=\"estrellas-podio-avatar\">' + escHtml(initials(item.full_name)) + '</div></div>'
				+   '<h3 class=\"estrellas-podio-name\">' + escHtml(item.full_name) + '</h3>'
				+   '<p class=\"estrellas-podio-points\">' + escHtml(fmtPoints(item.total)) + '</p>'
				+   '<div class=\"estrellas-podio-base ' + cls + '\">' + rank + '</div>'
				+ '</div>';
		}
		function otherCard(item, rank){
			if(!item){ return ''; }
			return ''
				+ '<div class=\"estrellas-podio-other\">'
				+   '<span class=\"estrellas-podio-badge\">' + rank + '° puesto</span>'
				+   '<div><strong>' + escHtml(item.full_name) + '</strong></div>'
				+   '<div style=\"margin-top:4px;color:#ffdd85;font-weight:700;\">' + escHtml(fmtPoints(item.total)) + '</div>'
				+ '</div>';
		}
		function tableRows(rows){
			return rows.map(function(r, i){
				return ''
					+ '<tr>'
					+   '<td>' + (i + 1) + '</td>'
					+   '<td>' + escHtml(r.full_name) + '</td>'
					+   '<td>' + escHtml(fmtPoints(r.total)) + '</td>'
					+   '<td>' + escHtml(r.company_name || '') + '</td>'
					+ '</tr>';
			}).join('');
		}
		async function load(){
			var root = document.getElementById('estrellas-nw-podio-root');
			var typeSelect = document.getElementById('estrellas-nw-podio-type');
			if(!root){ return; }
			root.innerHTML = '<p class=\"estrellas-podio-loading\">Cargando ranking...</p>';
			try{
				var endpoint = new URL(EstrellasNWPodio.restRanking, window.location.origin);
				var selectedType = typeSelect ? String(typeSelect.value || '').trim().toUpperCase() : '';
				if(selectedType){
					endpoint.searchParams.set('starCode', selectedType);
				}
				var res = await fetch(endpoint.toString(), {
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': EstrellasNWPodio.nonce
					}
				});
				if(!res.ok){ throw new Error('HTTP ' + res.status); }
				var ranking = await res.json();
				if(!Array.isArray(ranking) || ranking.length === 0){
					root.innerHTML = '<p class=\"estrellas-podio-empty\">Aun no hay posiciones para mostrar.</p>';
					return;
				}
				var top1 = ranking[0] || null;
				var top2 = ranking[1] || null;
				var top3 = ranking[2] || null;
				var r4 = ranking[3] || null;
				var r5 = ranking[4] || null;
				var r6 = ranking[5] || null;
				root.innerHTML = ''
					+ '<div class=\"estrellas-podio-top3\">'
					+   podiumCard(top2, 2)
					+   podiumCard(top1, 1)
					+   podiumCard(top3, 3)
					+ '</div>'
					+ '<div class=\"estrellas-podio-others\">'
					+   otherCard(r4, 4)
					+   otherCard(r5, 5)
					+   otherCard(r6, 6)
					+ '</div>'
					+ '<div class=\"estrellas-podio-table-wrap\">'
					+   '<table class=\"estrellas-podio-table\">'
					+     '<thead><tr><th>#</th><th>Funcionario</th><th>Puntos</th><th>Empresa</th></tr></thead>'
					+     '<tbody>' + tableRows(ranking) + '</tbody>'
					+   '</table>'
					+ '</div>';
			}catch(err){
				root.innerHTML = '<p class=\"estrellas-podio-empty\">No se pudo cargar el ranking. Verifica que el usuario este logueado.</p>';
			}
		}
		if(document.readyState === 'loading'){
			document.addEventListener('DOMContentLoaded', load);
		}else{
			load();
		}
		var typeSelect = document.getElementById('estrellas-nw-podio-type');
		if(typeSelect){
			typeSelect.innerHTML = STAR_TYPE_OPTIONS.map(function(opt){
				return '<option value=\"' + escHtml(opt.value) + '\">' + escHtml(opt.label) + '</option>';
			}).join('');
			typeSelect.addEventListener('change', load);
		}
	})();
	";
	wp_add_inline_script($handle, $js);

	return '<section class="estrellas-podio-wrap"><h2 class="estrellas-podio-title">Ranking de funcionarios</h2><p class="estrellas-podio-subtitle">Reconociendo el talento y el compromiso</p><div class="estrellas-podio-filters"><label for="estrellas-nw-podio-type">Categoria</label><select id="estrellas-nw-podio-type"></select></div><div id="estrellas-nw-podio-root"></div></section>';
});
