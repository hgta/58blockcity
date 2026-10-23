// 访客城市定位（服务端 IP 定位）
//
// 变更说明（change: city-locate-server-side-ip）：
//   此前在前端直连高德（key 明文暴露、每 PV 两次调用），配额超限后
//   ipData.city 为 undefined，city.replace() 抛异常滑进 catch，进而调用
//   navigator.geolocation —— 每位访客都会被弹「获取您的位置」授权框。
//   现改为调用服务端接口，定位失败只显示「未知城市」，不再触发任何授权。
var CITY_GEO_ENDPOINT = 'https://www.58.tl/api/city_geo.php';
var CITY_GEO_CACHE_KEY = 'cityGeoCache';
var CITY_GEO_CACHE_TTL = 7 * 24 * 60 * 60 * 1000; // 7 天

function applyCityInfo(city, citycode) {
  var cityEl = document.getElementById('userCity');
  if (cityEl && city) cityEl.textContent = String(city).replace('市', '');
  if (!citycode) return;
  var href = 'https://www.blockcity.biz/' + citycode + '?iclc';
  var link = document.getElementById('cityLink');
  if (link) link.href = href;
  var link2 = document.getElementById('cityLink2');
  if (link2) link2.href = href;
}

function readCityGeoCache() {
  try {
    var raw = localStorage.getItem(CITY_GEO_CACHE_KEY);
    if (!raw) return null;
    var data = JSON.parse(raw);
    if (!data || !data.city || !data.ts) return null;
    if (Date.now() - data.ts > CITY_GEO_CACHE_TTL) return null;
    return data;
  } catch (e) {
    return null;
  }
}

function writeCityGeoCache(city, citycode) {
  try {
    localStorage.setItem(CITY_GEO_CACHE_KEY, JSON.stringify({
      city: city, citycode: citycode || '', ts: Date.now()
    }));
  } catch (e) {
    /* 隐私模式下 localStorage 不可用时忽略 */
  }
}

// 获取当前城市（城市名 + 电话区号），用于欢迎条与城市链接
function getCityInfo() {
  if (!document.getElementById('userCity')) return;

  var cached = readCityGeoCache();
  if (cached) {
    applyCityInfo(cached.city, cached.citycode);
    return;
  }

  fetch(CITY_GEO_ENDPOINT, { credentials: 'include' })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data && data.ok && data.city) {
        applyCityInfo(data.city, data.citycode || '');
        writeCityGeoCache(data.city, data.citycode || '');
        return;
      }
      applyCityInfo('未知城市', '');
    })
    .catch(function () {
      applyCityInfo('未知城市', '');
    });
}
