// 获取当前城市的adcode和电话区号
function getCityInfo() {
  // 步骤1：通过IP定位获取当前城市adcode
  fetch('https://restapi.amap.com/v3/ip?key=c7ebabae441606f172364f6d644a9ce4')
	.then(response => response.json())
	.then(ipData => {
	  const city = ipData.city; // 当前城市名（如"北京市"）
	  const adcode = ipData.adcode; // 当前adcode
	  
	  document.getElementById('userCity').textContent = city.replace('市', '');

	  // 步骤2：通过地理编码查询电话区号
	  return fetch('https://restapi.amap.com/v3/geocode/geo?address='+city+'&key=c7ebabae441606f172364f6d644a9ce4');    
	})
	.then(response => response.json())
	.then(geoData => {
	  const citycode = geoData.geocodes[0].citycode;
	  document.getElementById('cityLink').href = 'https://www.blockcity.pub/'+citycode+'?iclc';  
	})
	.catch(error => {
		if(navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(position => {
				fetch('https://restapi.amap.com/v3/geocode/regeo?key=c7ebabae441606f172364f6d644a9ce4&location='+position.coords.longitude+','+position.coords.latitude)
					.then(response => response.json())
					.then(data => {
						if(data.regeocode && data.regeocode.addressComponent.city) {
							const city = data.regeocode.addressComponent.city.replace('市', '');
							document.getElementById('userCity').textContent = city;
							document.getElementById('cityLink').href = 'https://www.blockcity.pub/'+data.regeocode.addressComponent.citycode+'?iclc';
						}
					});
			});
		} 
	});
}