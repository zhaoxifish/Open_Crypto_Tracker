# BTC监测器界面

这层界面将原项目改为中文浅色监测工作台。它使用原有 PHP、jQuery 和 Bootstrap，不增加框架、包管理器、远程字体或构建依赖。行情计算、表单字段、登录校验和上游更新机制继续使用原实现。

## 设计约定

- 背景 `#f4f7fb`、面板 `#ffffff`、正文 `#16263b`、辅助文字 `#61718a`、主色 `#1665d8`、数据点缀 `#0e9ba4`。
- 正文 15px，表单和导航 14px，辅助说明 12–13px，页面标题 27px；优先使用设备上的中文字体，数字使用等宽数字特性。
- 桌面为固定导航与流式内容区，窄屏为抽屉导航；数据表可在自身区域横向滚动，资产编辑按屏幕宽度分为 4/2/1 列。
- 资产数字、价格和图表使用真实应用数据；未录入资产时显示引导，不填充虚构指标。
- 保留可见焦点、原生表单语义、减少动画偏好、错误反馈与未保存提醒。

参考了 GitHub 上的 [Anthropic Frontend Design](https://github.com/anthropics/skills/tree/main/skills/frontend-design) 的设计流程，以及 [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) 的界面检查规范。它们仅用于设计参考，不是应用的运行依赖，也未安装到用户的全局 skill 目录。

## 文件分工

| 文件 | 作用 |
| --- | --- |
| `shell.css` | 页面外壳、导航、标题、提示、响应式布局 |
| `forms.css` | 设置、资产字段、表格、弹窗、登录表单 |
| `workspace.js` | 新导航交互、局部表格滚动、子页状态、浅色图表显示 |
| `mark.svg`、`icons.svg` | 本地绘制的品牌与操作图标 |
| `templates/interface/php/wrap/wrap-elements/btc-topbar.php` | 全局操作栏 |
| `templates/interface/php/wrap/wrap-elements/btc-about.php` | 本地关于、隐私说明和开源许可 |

所有样式以 `html.btc-monitor` 为作用域。较高的选择器优先级用于覆盖旧主题中大量 `!important` 规则，避免直接重写上游整套样式。

## 与上游的接点

公共 header 添加主题标记，head 在旧资源后加载新样式，在 `init.js` 前加载新界面脚本。`navigation-bars.php` 保留原始路由、nonce、菜单选择器；操作栏保留原保存、隐私和提醒事件。

`functions.js` 中只调整新界面的字号、标题定位和 iframe 高度，保存按钮状态选择器不再绑定旧侧栏。`init.js` 在新界面使用原生侧栏滚动，容忍已移除的紧凑侧栏节点。

`runtime-type-init.php` 对 UI/AJAX 请求应用浅色显示，不更改后台定时任务的配色配置。常规设置保留旧字体、字号和主题的原值作为隐藏字段，避免出现修改后不生效的可见控件。

合并官方更新时，优先审查这些接点；保留上游表单的 name、value、ID、nonce 和校验，不将界面中文写入内部配置枚举。中文词库仍通过 `node app-lib/i18n/build.mjs` 生成。

## 署名与许可

原项目 `LICENSE` 和源代码版权注释保留。原作者捐赠、个人网站、宣传图片和推广弹条从日常界面移除。原项目署名与仍使用的第三方素材说明集中在“关于与开源许可”；ZingChart 的必要图表标识不隐藏。

## 验证

既有汉化行为测试：`node app-lib/i18n/tests/test.mjs`。还需在独立测试缓存的本地实例中检查资产保存、后台 iframe 保存、未保存确认、登录验证，以及桌面与手机的实际页面。不要为了视觉测试修改正式实例中的账号或资产。
