52bus-shanghai-weibo2twitter
============================

基于 php 和 JavaScript 的同步魔都交通论坛特定微博到 twitter 的应用

介绍
----
* 本应用从[魔都交通论坛]官方[微博]读取微博，将其中的特定微博转发至 Twitter
* 转发的微博包括以下类型：
  * 线路营运动态
  * 魔坛图片精选
  * 魔坛精选
  * 魔坛活动
  * 魔坛科普
  * 巴士模型

说明
----
* 访问 auth.php 进行 Twitter 和微博的授权
* 应用的 key 和已授权用户的 token 均保存在 oauth/config.php 中
* 授权模块可脱离同步应用独立使用（见 authonly 分支）
* 访问 index.php 使用本应用发推

更新日志
--------
### 2013-05-22
* 更新 Twitter API 至 1.1 版本
* 更新 libweibo 和 tmhOauth 至当前最新版本
* 添加魔坛科普话题的抓取

### 2012-08-28
* 添加魔坛精选话题的抓取

### 2012-08-26
* 加入不发布某条微博的选项
* 修正最大字数
* 添加授权页面和同步应用页面的相互链接
* 补上遗漏的抓取“巴士模型”微博的功能
* 发布微博时，若微博超过字数限制，弹出警告框
* 发布微博时，不会验证勾选“不发布”微博的字数
* 修正全部勾选“不发布”后，发布微博时的错误

### 2012-08-25
* 授权模块代码微调
* 提供获取微博时的选项
* 重写抓取并编辑微博的功能
* 添加字数统计和限制的功能
* 重写发推功能
* 同步应用已可完整运作

### 2012-08-24
* 重写授权模块的代码
* 暂时删除同步应用模块的代码

### 2012-08-22
* 首次上传测试版

许可协议
--------
* 52bus-shanghai-weibo2twitter 遵循 [GNU通用公共许可证第3版] 发布
* [tmhOAuth] 库由 [Matt Harris] 编写，采用 [Apache许可证2.0版]
* [libweibo] 由微博官方编写，采用 [MIT许可证]

发布页面
--------
[魔都交通论坛微博同步应用]

[魔都交通论坛]: http://sh.52bus.com/
[微博]: http://weibo.com/mdjtlt
[tmhOAuth]: https://github.com/themattharris/tmhOAuth
[Matt Harris]: https://github.com/themattharris
[GNU通用公共许可证第3版]: https://www.gnu.org/licenses/gpl-3.0.html
[Apache许可证2.0版]: http://www.apache.org/licenses/LICENSE-2.0
[libweibo]: https://code.google.com/p/libweibo/
[MIT许可证]: http://opensource.org/licenses/mit-license.php
[魔都交通论坛微博同步应用]: http://lyonna.me/2012/08/52bus-shanghai-weibo2twitter/
