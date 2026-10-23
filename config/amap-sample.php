<?php
/**
 * 高德 Web 服务 API 配置（服务端 IP 定位）
 *
 * 使用方式：复制为 config/amap.php 并填入 key（真实文件不入库）。
 * 若未配置，api/city_geo.php 会直接返回「未知城市」，不会报错、不会外呼。
 *
 * 重要：请在高德控制台（console.amap.com）为该 key 绑定域名白名单
 *（www.58.tl / *.58.tl）。此前 key 明文写在前端 JS 里可被任意盗用，
 * 导致日配额被吃光，IP 定位失败后前端降级到浏览器定位，弹出授权框。
 */

define('AMAP_WEB_KEY', '');
