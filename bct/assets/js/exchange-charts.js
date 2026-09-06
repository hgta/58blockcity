/**
 * 58BCT 交易所图表工具
 * 基于 ECharts
 */

(function (window) {
  'use strict';

  var BCTCharts = {
    colors: {
      up: '#0ecb81',
      down: '#f6465d',
      accent: '#f0b90b',
      text: '#eaecef',
      textSecondary: '#848e9c',
      grid: '#2b3139',
      bg: '#151a21'
    },

    /**
     * 初始化价格走势图（折线/面积）
     * @param {string|HTMLElement} el
     * @param {Array} data [{time, close}]
     * @param {string} title
     */
    initPriceChart: function (el, data, title) {
      var chart = echarts.init(typeof el === 'string' ? document.getElementById(el) : el, 'dark');
      var times = data.map(function (d) { return d.time; });
      var prices = data.map(function (d) { return d.close; });

      var option = {
        backgroundColor: 'transparent',
        tooltip: {
          trigger: 'axis',
          backgroundColor: 'rgba(21, 26, 33, 0.9)',
          borderColor: '#2b3139',
          textStyle: { color: '#eaecef' },
          formatter: function (params) {
            return params[0].name + '<br/>价格: ¥' + parseFloat(params[0].value).toFixed(4);
          }
        },
        grid: { left: 10, right: 10, top: 20, bottom: 20, containLabel: true },
        xAxis: {
          type: 'category',
          data: times,
          axisLine: { lineStyle: { color: '#2b3139' } },
          axisLabel: { color: '#848e9c', fontSize: 10 },
          axisTick: { show: false }
        },
        yAxis: {
          type: 'value',
          scale: true,
          splitLine: { lineStyle: { color: '#2b3139', type: 'dashed' } },
          axisLabel: { color: '#848e9c', fontSize: 10, formatter: '¥{value}' }
        },
        series: [{
          name: '价格',
          type: 'line',
          data: prices,
          smooth: true,
          symbol: 'none',
          lineStyle: { width: 2, color: '#f0b90b' },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(240, 185, 11, 0.3)' },
              { offset: 1, color: 'rgba(240, 185, 11, 0)' }
            ])
          }
        }]
      };

      chart.setOption(option);
      window.addEventListener('resize', function () { chart.resize(); });
      return chart;
    },

    /**
     * 初始化 K 线图
     * @param {string|HTMLElement} el
     * @param {Array} data [{time, open, high, low, close, volume}]
     */
    initCandleChart: function (el, data) {
      var chart = echarts.init(typeof el === 'string' ? document.getElementById(el) : el, 'dark');
      var categoryData = data.map(function (d) { return d.time; });
      var values = data.map(function (d) { return [d.open, d.close, d.low, d.high]; });
      var volumes = data.map(function (d, i) { return [i, d.volume, d.close > d.open ? 1 : -1]; });

      var option = {
        backgroundColor: 'transparent',
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'cross' },
          backgroundColor: 'rgba(21, 26, 33, 0.9)',
          borderColor: '#2b3139',
          textStyle: { color: '#eaecef' }
        },
        grid: [{ left: 10, right: 10, top: 20, height: '60%' },
               { left: 10, right: 10, top: '75%', height: '15%' }],
        xAxis: [{
          type: 'category',
          data: categoryData,
          scale: true,
          boundaryGap: false,
          axisLine: { lineStyle: { color: '#2b3139' } },
          axisLabel: { color: '#848e9c', fontSize: 10 },
          splitLine: { show: false },
          min: 'dataMin',
          max: 'dataMax'
        }, {
          type: 'category',
          gridIndex: 1,
          data: categoryData,
          axisLabel: { show: false }
        }],
        yAxis: [{
          scale: true,
          splitArea: { show: false },
          splitLine: { lineStyle: { color: '#2b3139', type: 'dashed' } },
          axisLabel: { color: '#848e9c', fontSize: 10, formatter: '¥{value}' }
        }, {
          scale: true,
          gridIndex: 1,
          splitNumber: 2,
          axisLabel: { show: false },
          axisLine: { show: false },
          splitLine: { show: false }
        }],
        dataZoom: [{ type: 'inside', xAxisIndex: [0, 1], start: 0, end: 100 }],
        series: [{
          name: 'K线',
          type: 'candlestick',
          data: values,
          itemStyle: {
            color: '#0ecb81',
            color0: '#f6465d',
            borderColor: '#0ecb81',
            borderColor0: '#f6465d'
          }
        }, {
          name: '成交量',
          type: 'bar',
          xAxisIndex: 1,
          yAxisIndex: 1,
          data: volumes,
          itemStyle: {
            color: function (params) {
              return params.value[2] > 0 ? '#0ecb81' : '#f6465d';
            }
          }
        }]
      };

      chart.setOption(option);
      window.addEventListener('resize', function () { chart.resize(); });
      return chart;
    },

    /**
     * 初始化持仓饼图
     * @param {string|HTMLElement} el
     * @param {Array} data [{name, value}]
     */
    initPortfolioPie: function (el, data) {
      var chart = echarts.init(typeof el === 'string' ? document.getElementById(el) : el, 'dark');
      var option = {
        backgroundColor: 'transparent',
        tooltip: {
          trigger: 'item',
          backgroundColor: 'rgba(21, 26, 33, 0.9)',
          borderColor: '#2b3139',
          textStyle: { color: '#eaecef' },
          formatter: '{b}: ¥{c} ({d}%)'
        },
        legend: {
          orient: 'vertical',
          right: 10,
          top: 'center',
          textStyle: { color: '#848e9c' }
        },
        series: [{
          name: '持仓分布',
          type: 'pie',
          radius: ['40%', '70%'],
          center: ['35%', '50%'],
          avoidLabelOverlap: false,
          itemStyle: { borderRadius: 5, borderColor: '#151a21', borderWidth: 2 },
          label: { show: false },
          emphasis: { label: { show: true, fontSize: 14, fontWeight: 'bold', color: '#eaecef' } },
          data: data
        }]
      };
      chart.setOption(option);
      window.addEventListener('resize', function () { chart.resize(); });
      return chart;
    },

    /**
     * 初始化深度图
     * @param {string|HTMLElement} el
     * @param {Array} bids [{price, cumulative_amount}]
     * @param {Array} asks [{price, cumulative_amount}]
     */
    initDepthChart: function (el, bids, asks) {
      var chart = echarts.init(typeof el === 'string' ? document.getElementById(el) : el, 'dark');
      var bidPrices = bids.map(function (d) { return d.price; }).reverse();
      var bidAmounts = bids.map(function (d) { return d.cumulative_amount; }).reverse();
      var askPrices = asks.map(function (d) { return d.price; });
      var askAmounts = asks.map(function (d) { return d.cumulative_amount; });

      var option = {
        backgroundColor: 'transparent',
        tooltip: {
          trigger: 'axis',
          backgroundColor: 'rgba(21, 26, 33, 0.9)',
          borderColor: '#2b3139',
          textStyle: { color: '#eaecef' }
        },
        grid: { left: 10, right: 10, top: 20, bottom: 20, containLabel: true },
        xAxis: {
          type: 'value',
          scale: true,
          splitLine: { lineStyle: { color: '#2b3139', type: 'dashed' } },
          axisLabel: { color: '#848e9c', fontSize: 10, formatter: '¥{value}' }
        },
        yAxis: {
          type: 'value',
          splitLine: { lineStyle: { color: '#2b3139', type: 'dashed' } },
          axisLabel: { color: '#848e9c', fontSize: 10 }
        },
        series: [{
          name: '买盘',
          type: 'line',
          data: bidPrices.map(function (p, i) { return [p, bidAmounts[i]]; }),
          step: 'start',
          symbol: 'none',
          lineStyle: { color: '#0ecb81', width: 2 },
          areaStyle: { color: 'rgba(14, 203, 129, 0.2)' }
        }, {
          name: '卖盘',
          type: 'line',
          data: askPrices.map(function (p, i) { return [p, askAmounts[i]]; }),
          step: 'start',
          symbol: 'none',
          lineStyle: { color: '#f6465d', width: 2 },
          areaStyle: { color: 'rgba(246, 70, 93, 0.2)' }
        }]
      };

      chart.setOption(option);
      window.addEventListener('resize', function () { chart.resize(); });
      return chart;
    }
  };

  window.BCTCharts = BCTCharts;
})(window);
