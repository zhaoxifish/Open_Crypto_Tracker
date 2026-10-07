# 本地开发与同步上游

本地源码：`D:\git-work\Open_Crypto_Tracker`。个人 Fork 为 [zhaoxifish/Open_Crypto_Tracker](https://github.com/zhaoxifish/Open_Crypto_Tracker)，上游为 [taoteh1221/Open_Crypto_Tracker](https://github.com/taoteh1221/Open_Crypto_Tracker)。

## 分支与初始基线

- `origin`：个人 Fork；`upstream`：官方仓库。
- `main`：保留官方源码，使用快进方式同步。
- `develop`：开发分支，跟踪 `origin/develop`；本地运行环境和后续功能变更放在此分支。
- 初始基线：`659c506529a99603d9ef9f19ca9899ac9c86dd35`，上游 `main` 的 `v6.01.10-dev-pre-rev-3` 开发修订，**不是稳定发布标签**。
- 克隆使用 `--filter=blob:none`，保留完整提交历史，历史文件内容按需下载；不是浅克隆。

可随时检查当前状态：

```powershell
Set-Location 'D:\git-work\Open_Crypto_Tracker'
git remote -v
git branch -vv
git status --short
```

## 运行原版

前提：Git、Docker Desktop，以及已启动的 Linux 容器引擎。采用官方 Server Edition 所需的 PHP/Apache 环境；无需安装新的 npm 或 Composer 应用依赖。

```powershell
Set-Location 'D:\git-work\Open_Crypto_Tracker'
git switch develop
docker compose up -d --build app
docker compose ps
```

访问 [https://localhost:8443](https://localhost:8443)。入口 [http://localhost:8088](http://localhost:8088) 会跳转至 HTTPS。证书由本地容器首次启动时生成；本机当前 Windows 账户已在 2026-10-07 配置信任，新电脑首次使用请按下节导入本机项目的证书。

首次页面会要求创建管理员；由使用者自行设置用户名、密码并填写验证码。首次加载需要初始化缓存和访问外部行情服务，可能比后续访问慢。

运行环境包括 PHP 8.3、Apache、HTTPS、`mod_rewrite` 和 `.htaccess` 支持。启用 `curl`、`mbstring`、XML/DOM/SimpleXML、`zip`、带 FreeType/JPEG 的 `gd`，以及 MySQL 驱动；无需为默认功能另建数据库服务。镜像基线以 `.docker/Dockerfile` 中的摘要为准。

PHP 参数采用官方模板值：256M 内存、7M 上传、15M POST、50 秒输入限制、350 秒页面执行上限。应用内会按运行模式调整相关参数。

源码目录挂载至容器，编辑 PHP、模板或静态文件后刷新页面即可；修改 Dockerfile 或运行配置后，先执行 `docker compose --profile cron down`，再执行 `docker compose up -d --build app`；如需后台采集，最后重新启动 cron，以重建它共享的网络。

## Windows / Chrome 消除证书提醒

本机已将项目当前的公开证书加入 `Cert:\CurrentUser\Root`。Windows 默认 TLS 校验和使用全新用户目录的 Chrome 均已通过；验证时未使用 `--insecure`、`--ignore-certificate-errors` 或关闭证书检查。Chrome 支持读取当前用户的根证书信任设置，见 [Chrome 官方说明](https://chromium.googlesource.com/chromium/src/+/main/net/data/ssl/chrome_root_store/faq.md#how-does-the-chrome-certificate-verifier-integrate-with-platform-trust-stores-for-local-trust-decisions)。

现在刷新页面即可。如果旧标签页仍显示原来的警告，先关闭该标签页再重新访问；仍有缓存时，保存其他页面工作并退出、重新打开 Chrome。

换电脑或重新生成证书时，在当前 Windows 账户的 PowerShell 中执行以下步骤。只导入从自己这个项目的 Docker 容器直接导出并核对过的证书；这会修改当前用户的根证书信任列表。私钥继续留在 Docker 数据卷中。

```powershell
Set-Location 'D:\git-work\Open_Crypto_Tracker'
New-Item -ItemType Directory -Force '.local\tls' | Out-Null
docker compose exec -T app openssl x509 -in /etc/apache2/local-certs/localhost.crt -noout -subject -issuer -dates -fingerprint -sha256 -ext subjectAltName
docker compose cp app:/etc/apache2/local-certs/localhost.crt .local/tls/localhost.crt
$octCert = [System.Security.Cryptography.X509Certificates.X509Certificate2]::new((Resolve-Path '.local\tls\localhost.crt').Path)
$octSha = [System.Security.Cryptography.SHA256]::Create()
[System.BitConverter]::ToString($octSha.ComputeHash($octCert.RawData))
$octSha.Dispose()
$octCert | Format-List Subject, Issuer, Thumbprint, NotBefore, NotAfter
```

核对导出证书的 SHA-256 与容器输出一致，域名是 `localhost` / `127.0.0.1`，证书在有效期内，然后导入：

```powershell
Import-Certificate -FilePath '.local\tls\localhost.crt' -CertStoreLocation 'Cert:\CurrentUser\Root'
curl.exe --noproxy '*' --silent --show-error --output NUL --write-out 'HTTP %{http_code}\n' https://localhost:8443/templates/interface/media/images/auto-preloaded/bitcoin-btc-logo.png
```

如 Windows 显示证书安装确认框，核对信息后确认。HTTP 200 表示 Windows 默认校验已接受该证书，再用 Chrome 正常访问网站。无需修改浏览器的全局安全选项。

当前证书有效期至 **2029-01-09**。普通容器重启、重建会保留证书；删除 `local-certs` 数据卷后产生的新证书，需要重新核对并导入。证书到期时也需要更新，避免误删保存管理员配置的 `cache` 数据卷。

需要撤销本次信任时，仅删除对应指纹的条目（下面是本机 2026-10-07 导入的证书，不匹配其他 `localhost` 证书）：

```powershell
Remove-Item -LiteralPath 'Cert:\CurrentUser\Root\6E645CE8FF714872FD2D5D9E8C518A91AF0A0078'
```

## 数据、日志与停止

`cache` 命名卷保存配置、管理员登录数据、行情缓存和图表；`local-certs` 命名卷保存本地证书。凭据、缓存、证书和备份不应提交至 Git。

```powershell
docker compose logs --tail 100 app
docker compose exec app sh -c 'tail -n 100 /var/www/html/cache/logs/app_log.log'
docker compose --profile cron down
```

`down` 停止并移除容器，但保留命名卷。再次运行 `up` 会继续使用已有数据。不要在需要保留配置和图表时使用 `down -v`。

应用首次启动还会在源码根目录生成 `.htaccess`、`.user.ini`，并短暂创建域名检查文件。这些属于本地运行产物，已加入 Git 忽略规则。原版还会尝试删除 `.dev-status.json`，本开发环境将它单独只读挂载，保护 Git 工作区；Apache 同时禁止从网页访问该文件。

## 可选：图表采集与提醒

按官方说明，后台图表数据和提醒依赖 `cron.php`。先完成管理员初始化和所需配置，再启用：

```powershell
docker compose --profile cron up -d
docker compose logs --tail 100 cron
```

后台容器首次启动即执行一次，此后每次执行结束等待20分钟再运行。它与网页服务共享网络和缓存；电脑及 Docker Desktop 必须保持运行。邮件、Telegram 等提醒渠道需要另外填写自己的配置。

仅停止后台采集：

```powershell
docker compose --profile cron stop cron
```

## 更新前备份

关闭正在使用的页面、暂停后台任务，然后备份缓存。以下示例将备份放在仓库外：

```powershell
docker compose --profile cron stop cron
New-Item -ItemType Directory -Force 'D:\git-work\backups\Open_Crypto_Tracker' | Out-Null
$octBackupPath = 'D:\git-work\backups\Open_Crypto_Tracker\cache-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.tar.gz'
docker compose exec -T app tar -czf /tmp/oct-cache-backup.tar.gz -C /var/www/html cache
docker compose cp app:/tmp/oct-cache-backup.tar.gz $octBackupPath
Get-Item -LiteralPath $octBackupPath
```

该备份包含私人配置和登录数据，请妥善保存。也可在管理员的“Reset / Backup & Restore”页面导出配置和图表备份。官方提醒：不同版本的旧配置不能未经检查直接恢复。

## 同步官方更新

先备份并提交自己的源码改动，确认 `git status --short` 无待处理变更，再同步。不要在存在未保存更改时切换分支。

```powershell
docker compose --profile cron down
git fetch upstream
git switch main
git merge --ff-only upstream/main
git switch develop
git merge main
docker compose up -d --build app
```

`--ff-only` 失败时先检查分支差异；合并出现冲突时解决冲突并提交后，再继续启动验证。不要用强制重置覆盖自己的改动。

验证 HTTPS 首页、验证码、管理员登录和行情数据，检查应用及容器日志，再推送个人 Fork：

```powershell
git push origin main develop
```

如需后台采集，再执行 `docker compose --profile cron up -d`。

## Git 网络设置

本仓库按本机代理配置使用 `http://127.0.0.1:7892`。这是仓库局部 Git 设置；不包含在提交中，也不自动配置 Docker 或应用行情请求。

```powershell
git config --local --get http.proxy
```

停用本机代理后，移除该仓库设置：

```powershell
git config --local --unset http.proxy
```

重新启用时：

```powershell
git config --local http.proxy http://127.0.0.1:7892
```

## 本次验证记录

2026-10-07（Asia/Shanghai）验证：

- Docker 镜像构建完成，PHP 8.3.35；必要扩展和 GD FreeType/JPEG 检查通过，Apache 配置语法正确。
- HTTPS 首页 HTTP 200，显示原版 Admin Login Creation 页面，已用 Chrome 实际渲染确认。
- CAPTCHA HTTP 200，返回有效 PNG（525 × 135）；HTTP 8088 正常 301 跳转至 HTTPS。
- cache、plugins 的保护检查文件及 Git / Docker 配置访问返回 403。
- 容器内 CoinGecko ping 和 Kraken BTC/USD 行情接口均返回 HTTP 200。
- 服务健康检查通过；应用 PHP、JS、模板均未修改。
- 日志中原版 `init.php` 有未定义变量 Warning；检查期间未见 PHP Fatal/Parse Error。保留这些原版提示以便后续排查。

管理员尚未创建，需使用者在首次页面自行设置；因此管理员登录后的资产组合流程未验证。后台 cron 默认未启用，邮件/Telegram 等提醒未配置、未测试。

## 官方依据

- [README：安装与后台任务](https://github.com/taoteh1221/Open_Crypto_Tracker/blob/main/README.txt)
- [系统要求检查](https://github.com/taoteh1221/Open_Crypto_Tracker/blob/main/app-lib/php/inline/system/system-checks.php)
- [PHP 参数模板](https://github.com/taoteh1221/Open_Crypto_Tracker/blob/main/templates/back-end/root-app-directory-user-ini.template)
- [故障排查](https://github.com/taoteh1221/Open_Crypto_Tracker/blob/main/TROUBLESHOOTING.txt)
