<dialog id="btc-about" class="btc-about" aria-labelledby="btc-about-title">
  <div class="btc-about-header"><h2 id="btc-about-title">关于 BTC监测器</h2><button type="button" class="btc-button btc-icon-button" data-btc-close-about aria-label="关闭"><svg class="btc-icon" aria-hidden="true"><use href="app-lib/ui/icons.svg#close" /></svg></button></div>
  <p>在自己的设备上查看行情、管理资产与接收提醒。</p>
  <h3>使用与隐私</h3>
  <p>管理员登录需要浏览器允许 Cookie。资产数据是否保存在浏览器中，由「偏好设置」中的 Cookie 选项控制。访问记录只存放在本应用中，供管理员查看。</p>
  <p>「隐私模式」会隐藏页面中的个人资产信息，并退出管理员登录。它不加密服务器上的原始数据。</p>
  <h3>开源许可</h3>
  <div translate="no" data-i18n="skip"><p>本项目基于 Open Crypto Tracker，原项目 Copyright 2014–2026 Mike Kilday，依照 GNU GPL v3 发布。本地版本更新了中文界面与视觉设计，原始版权声明及许可证保留于源代码中。</p></div>
  <p><a href="LICENSE" target="_blank" rel="noopener">查看 GNU GPL v3 许可原文</a></p>
  <p>部分图标由 <a href="https://icons8.com" target="_blank" rel="noopener">Icons8</a> 提供；图表使用 <a href="https://www.zingchart.com" target="_blank" rel="noopener">ZingChart</a>，图表内保留其必要标识。其他依赖的许可随源代码一同保留。</p>
  <small>当前内核版本：<?=htmlspecialchars($ct['app_version'], ENT_QUOTES, 'UTF-8')?></small>
</dialog>
