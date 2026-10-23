/**
 * 58互访圈系统 - 主JavaScript文件
 * 包含系统主要交互功能和工具函数
 */

document.addEventListener('DOMContentLoaded', function() {
    // 初始化城市定位功能（高德 IP + 经纬度降级）
    if (document.getElementById('userCity')) {
        getCityInfo();
    }

    // 初始化互访圈筛选功能
    initCircleFilters();

    // 初始化其他交互组件
    initComponents();

    // 将 PHP flash/session 消息转换为 Toast（无 JS 时保留原 alert）
    initFlashToasts();
});

/**
 * 初始化城市定位功能
 */
function initCityLocation() {
    // 浏览器定位（navigator.geolocation）会弹出授权框打扰访客，且此前的
    // 兜底实现是随机城市 mock，均不可用；统一走服务端 IP 定位 getCityInfo()
    if (typeof getCityInfo === 'function') {
        getCityInfo();
        return;
    }
    updateCityDisplay('未知城市');
}

/**
 * 更新城市显示
 */
function updateCityDisplay(cityName) {
    const cityElement = document.getElementById('userCity');
    if (cityElement) {
        cityElement.textContent = cityName;
        
        // 更新城市链接
        const cityLink = document.getElementById('cityLink');
        if (cityLink) {
            cityLink.href = `https://www.blockcity.biz/?city=${encodeURIComponent(cityName)}`;
        }
    }
}

/**
 * 初始化互访圈筛选功能
 */
function initCircleFilters() {
    // 城市筛选
    const cityTags = document.querySelectorAll('[data-city]');
    cityTags.forEach(tag => {
        tag.addEventListener('click', function() {
            // 移除所有active类
            document.querySelectorAll('[data-city]').forEach(t => t.classList.remove('active'));
            // 为当前点击的标签添加active类
            this.classList.add('active');
            
            // 获取当前城市
            const city = this.getAttribute('data-city');
            
            // 筛选互访圈
            filterCircles(city);
            
            // 更新URL参数
            updateUrlParam('city', city === 'all' ? '' : city);
        });
    });
    
    // 状态筛选
    const statusTags = document.querySelectorAll('[data-status]');
    statusTags.forEach(tag => {
        tag.addEventListener('click', function() {
            // 移除所有active类
            document.querySelectorAll('[data-status]').forEach(t => t.classList.remove('active'));
            // 为当前点击的标签添加active类
            this.classList.add('active');
            
            // 获取当前状态
            const status = this.getAttribute('data-status');
            
            // 筛选互访圈
            filterCircles(null, status);
            
            // 更新URL参数
            updateUrlParam('status', status === 'all' ? '' : status);
        });
    });
    
    // 从URL参数初始化筛选状态
    const urlParams = new URLSearchParams(window.location.search);
    const cityParam = urlParams.get('city');
    const statusParam = urlParams.get('status');
    
    if (cityParam) {
        document.querySelector(`[data-city="${cityParam}"]`)?.classList.add('active');
    }
    
    if (statusParam) {
        document.querySelector(`[data-status="${statusParam}"]`)?.classList.add('active');
    }
}

/**
 * 筛选互访圈
 */
function filterCircles(city, status) {
    const circles = document.querySelectorAll('.circle-card');
    let visibleCount = 0;
    
    circles.forEach(circle => {
        const circleCity = circle.getAttribute('data-city') || '';
        const circleStatus = circle.getAttribute('data-status') || '';
        
        // 城市筛选条件
        const cityMatch = !city || city === 'all' || circleCity === city;
        
        // 状态筛选条件
        const statusMatch = !status || status === 'all' || circleStatus === status;
        
        if (cityMatch && statusMatch) {
            circle.style.display = 'block';
            visibleCount++;
        } else {
            circle.style.display = 'none';
        }
    });
    
    // 更新显示数量
    updateVisibleCount(visibleCount);
}

/**
 * 更新可见数量显示
 */
function updateVisibleCount(count) {
    const countElement = document.getElementById('visibleCount');
    if (countElement) {
        countElement.textContent = count;
    }
}

/**
 * 更新URL参数
 */
function updateUrlParam(key, value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(key, value);
    } else {
        url.searchParams.delete(key);
    }
    window.history.pushState({}, '', url);
}

/**
 * 初始化其他交互组件
 */
function initComponents() {
    // 初始化工具提示
    initTooltips();
    
    // 初始化表单验证
    initFormValidation();
    
    // 初始化AJAX功能
    initAjaxHandlers();
}

/**
 * 初始化工具提示
 */
function initTooltips() {
    // 使用第三方库或原生实现工具提示
    // 这里只是示例，实际项目中可以使用tippy.js等库
    const elements = document.querySelectorAll('[data-tooltip]');
    elements.forEach(el => {
        el.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`;
            
            this.addEventListener('mouseleave', function() {
                document.body.removeChild(tooltip);
            }, { once: true });
        });
    });
}

/**
 * 初始化表单验证
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[needs-validation]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            this.classList.add('was-validated');
        });
    });
}

/**
 * 初始化AJAX处理器
 */
function initAjaxHandlers() {
    // 处理AJAX表单提交
    const ajaxForms = document.querySelectorAll('form[ajax-form]');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('[type="submit"]');
            
            // 禁用提交按钮
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
            }
            
            fetch(this.action, {
                method: this.method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('操作成功', 'success');
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                } else {
                    showToast(data.message || '操作失败', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('请求失败，请重试', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = this.getAttribute('data-original-text') || '提交';
                }
            });
        });
    });
}

/**
 * 显示Toast通知
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

/**
 * 工具函数 - 防抖
 */
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

/**
 * 工具函数 - 节流
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// 导出函数供其他模块使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initCityLocation,
        initCircleFilters,
        showToast,
        debounce,
        throttle
    };
}

// 获取当前城市的adcode和电话区号
// 访客城市定位（服务端 IP 定位）
// 变更说明（change: city-locate-server-side-ip）：
//   此前前端直连高德（key 明文暴露、每 PV 两次调用），配额超限后 city 为
//   undefined，city.replace() 抛异常滑进 catch 并调用 navigator.geolocation，
//   导致每位访客都被弹「获取您的位置」授权框。现改为调用服务端接口，
//   定位失败只显示「未知城市」，不再触发任何授权。
var CITY_GEO_ENDPOINT = 'https://www.58.tl/api/city_geo.php';
var CITY_GEO_CACHE_KEY = 'cityGeoCache';
var CITY_GEO_CACHE_TTL = 7 * 24 * 60 * 60 * 1000; // 7 天

function applyCityInfo(city, citycode) {
  var cityEl = document.getElementById('userCity');
  // 省级兜底（如「湖南省」）同样去掉行政后缀
  if (cityEl && city) cityEl.textContent = String(city).replace(/(市|省|自治区|特别行政区)$/, '');
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

// 警告框关闭功能
document.querySelectorAll('.alert .close').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.alert').style.display = 'none';
    });
});

// 自动隐藏所有模态框
document.querySelectorAll('.modal').forEach(modal => {
    modal.style.display = 'none';
});

// 显示控制
function showModal(id) {
    document.getElementById(id).style.display = 'block';
}
function hideModal(id) {
    document.getElementById(id).style.display = 'none';
}

/**
 * 复制文本到剪贴板，并显示 Toast 反馈
 */
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('链接已复制到剪贴板', 'success');
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showToast('链接已复制到剪贴板', 'success');
    } catch (err) {
        showToast('复制失败，请手动复制', 'error');
    }
    document.body.removeChild(textarea);
}

/**
 * 把页面中由 PHP session 写入的 flash 提示转换为 Toast
 * 无 JS 时原 alert 仍保留，保证可访问性
 */
function initFlashToasts() {
    document.querySelectorAll('.alert-success, .alert-info, .alert-danger, .alert-warning').forEach(alert => {
        const text = alert.textContent.trim();
        if (!text) return;
        let type = 'info';
        if (alert.classList.contains('alert-success')) type = 'success';
        if (alert.classList.contains('alert-danger')) type = 'error';
        if (alert.classList.contains('alert-warning')) type = 'error';
        showToast(text, type);
        // 保留原 alert 一段时间后再隐藏，避免与 Toast 重复
        alert.style.opacity = '0.6';
        setTimeout(() => {
            alert.style.display = 'none';
        }, 4000);
    });
}