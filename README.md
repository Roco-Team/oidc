<b><center>aaibOIDC <font size="3">v0.1β</font></center></b>
---
<center>一个给国内小站长提供的轻量OIDC服务</center><br>
<center><img src="https://files.srowo.cn/img/aaib.png" alt='AuthenticAIBullshit' width='30%'/></center>

---
#### Disclaimer

This program is 99% made by AI.
提点issue让我人间清醒

---
#### list

 - 可绑定微信openid (需自行扩展)
 - 支持Passkey登录 (Beta)
 - 可在登录特定应用时强制用户绑定微信
 - 支持Zeptomail API与SMTP发信

---
#### 初始化系统

1. 将源码复制到服务器上，并把网站目录设置为`/public`
2. 配置伪静态 `location / {try_files $uri /index.php$is_args$args;}location ~ \.well-known{allow all;try_files $uri $uri/ /index.php?$query_string;}`
3. 将自己的RSA密钥串存放到`/keys/public.key`与`/keys/private.pem`，或者cd到网站根目录运行`chmod +x ./rotatekeypair.sh && ./rotatekeypair.sh`
4. 按需修改网站根目录的`.env`文件
5. 修改`/config/routes.php`，注释或删除第61行的`exit();`
6. 给网站部署SSL证书，随后访问`https://<你的域名>/reset?confirm=1`，记录输出的所有内容
7. 回去`/config/routes.php`，取消注释或加回第61行的`exit();`

---
#### 默认管理员凭据

在 /reset 页面，如果数据库已经成功初始化，页面将会输出以下内容

```TEXT
Connected to database. Database schema initialized. Admin user created: Username: admin Password: admin123 TOTP Secret (add to Google Authenticator): xxxxxxxxxxxxx
```

其中

 - Username: admin -> 用户名
 - Password: admin123 -> 密码
 - TOTP Secret (add to Google Authenticator): xxxxxxxxxxxxx -> 两步验证密钥

两步验证密钥需要搭配两步验证器使用 (如 1Password / Google Authenticator)

![两步验证器教程(GA)](https://files.srowo.cn/img/2fa-setup-guide-cn.jpg)

系统管理后台 `https://<你的域名>/admin`

---
#### 修改/添加/删除管理员账户

_请直接操作数据库_

通过 `https://<你的域名>/ut/generator` 生成 TOTP 密钥(totp_secret) 和/或 密码哈希(password_hash)，然后在数据库的 `admin_users` 表中

 - 添加新管理员账户： 自定义一个username(用户名)，与刚刚生成的password_hash和totp_secret一起写入表中即可
 - 修改现有管理员账户： 将对应记录的password_hash或totp_secret更新为刚刚生成的即可

---
#### .ENV 配置

| 变量名 | 对应设置 | 注意事项 |
| --- | --- | --- |
| APP_URL | 站点地址 | **必填**，填写 `https://<你的域名>` (最后不要有斜杠) |
| SITE_NAME | 站点名称 | 选填 |
| SITE_LOGO | 站点图标 | 选填，建议使用横向图标 |
| JSD_URL | jsDelivr加速站 | 选填，不填则使用 https://cdn.jsdelivr.net 作为加速站 |
| DB_HOST | 数据库IP | **必填** |
| DB_PORT | 数据库端口 | **必填** |
| DB_DATABASE | 数据库名 | **必填** |
| DB_USERNAME | 数据库用户名 | **必填** |
| DB_PASSWORD | 数据库密码 | **必填** |
| OIDC_ISSUER | 凭据签发地址 | **必填**，填写 `https://<你的域名>` (最后不要有斜杠) |
| OAUTH2_ENC_KEY | ? | **别动** |
| USER_LOG_DISPLAY_LIMIT | 用户日志显示条数 | 选填，不填默认1条 |
| CN_ICP_NO | 中国大陆ICP备案号 | 选填，填写后会在页脚展示ICP备案号，请按示例填写: `粤ICP备0号` |
| CN_MPS_NO | 中国大陆公网安备号 | 选填，填写后会在页脚展示公网安备号，请按示例填写: `粤公网安备0号` |
| WECHAT_API_URL | 自建微信api地址 | 选填，不填会导致微信相关功能无法使用，需要目标服务器返回JSON `'openid' => $result` |
| WECHAT_API_TOKEN | api的token | 选填，将以 `$_GET['token']` 的方式传入api |
| SKIP_EMAIL_AUT | 跳过邮箱验证 | 选填，当设为 `1` 时会直接通过所有邮箱验证，但是验证邮件仍然会发送
| EMAIL_SENDER | 发件人地址 | 选填，不填会导致邮件无法发送 |
| EMAIL_SEND_METHOD | 发信方式 | 选填，不填默认curl方式，可填 `curl` `zoho` `smtp` |
| SMTP_HOST | SMTP服务器地址 | 当使用SMTP为发信方式时**必填** |
| SMTP_PORT | SMTP服务器端口 | 当使用SMTP为发信方式时**必填** |
| SMTP_USER | SMTP服务器用户 | 当使用SMTP为发信方式时**必填** |
| SMTP_PASS | SMTP服务器密码 | 当使用SMTP为发信方式时**必填** |
| EMAIL_API_URL | 邮件api地址 | 当使用curl或zoho为发信方式时**必填** |
| EMAIL_API_KEY | 邮件api密钥 | 当使用curl或zoho为发信方式时**必填** |
