function setMenu() {
	var menus = [
		{name: '短信管理', url: "sms.html", icon: "bi-mailbox"},
		{name: '发送短信', url: "send.html", icon: "bi-send"},
		{icon: "line"},
		{name: '<span class="text-muted">通讯录</span>', url: "contacts.html", icon: "bi-people"},
        {name: '<span class="text-muted">号码管理</span>', url: "phone.html", icon: "bi-phone"},
		{name: '<span class="text-muted">推送管理</span>', url: "push.html", icon: "bi-messenger"},
		{name: '<span class="text-muted">修改密码</span>', url: "password_change.html", icon: "bi-lock"},
	];

	var curl = window.location.href.substring(window.location.href.lastIndexOf('/')+1);
	var tpl = '<li class="nav-item" style="{4}">'
			+ '	<a href="{0}" class="nav-link {3}" data-url="{0}">'
			+ '		<i class="bi {2} me-1"></i> {1}'
			+ '	</a>'
			+ '</li>';
	var html = '';

	for(const item of menus) {
		if (item.icon == "line") {
			html += '<li class="border-top mt-1 mb-1"></li>';
		}
		else {
			html += tpl.Format(
				item.url,
				item.name,
				item.icon,
				curl.indexOf(item.url) == 0 ? " active": "",
				item.hasOwnProperty('layer') ? "text-indent: 5px": ""
			);
		}
	}

	$("#sidebarMenu .nav").html(html);
}

/*
 * 权限检测
 */
function checkUserRole(callback) {
	var token = getStorage('sms_token');
	
	if (token == '') {
		login();
	} else {
		$.ajax({
			type: "GET",
			url: "api/user.php",
			data:  {
				"do": "getrole",
				"token": getStorage("sms_token")
			},
			dataType: "jsonp",
			jsonpCallback: "response",
			beforeSend: function(xhr) {
				$("#loading").show();
			},
			success: function(json) {
				if (json.status == 'ok' && json.data.role >= 3) {
					console.log('passed!');

					// 设置用户名
					$('.username').text(' (' + json.data.username + ')');
					
					if (typeof callback === "function") {
						callback();
					}
				} else {
					login();
				}
			},
			error: function(xhr, textStatus) {
				console.log(xhr);
			},
			complete: function() {
				$("#loading").hide();
			}
		});
	}
}

/*
 * 跳转登录页
 */
function login() {
	window.location.href = 'login.html';
}

/*
 * 登出
 */
function logout() {
	layer.confirm("确定要登出吗？", function(result){
		if (result == true) {
			window.localStorage.removeItem("sms_token");
			window.location.reload();
		}
	});
}

/*
 * 取得URL参数
 */
function getQuery(variable) {
	var query = window.location.search.substring(1);
	var vars = query.split("&");
	for (var i=0;i<vars.length;i++) {
		var pair = vars[i].split("=");
		if(pair[0] == variable) {return decodeURI(pair[1]);}
	}
	return "";
}

/*
 * 取得LocalStorage数据
 */
function getStorage(item) {
	var storage = window.localStorage;

	if (storage && storage.getItem(item) != null) {
		return storage.getItem(item);
	} else {
		return "";
	}
}

/*
 * 字符串格式化
 */
String.prototype.Format = function(args) {
	var result = this;
	if (arguments.length > 0) {
		if (arguments.length == 1 && typeof (args) == "object") {
			for (var key in args) {
				if(args[key]!=undefined){
					var reg = new RegExp("({" + key + "})", "g");
					result = result.replace(reg, args[key]);
				}
			}
		} else {
			for (var i = 0; i < arguments.length; i++) {
				if (arguments[i] != undefined) {
					var reg= new RegExp("({)" + i + "(})", "g");
					result = result.replace(reg, arguments[i]);
				}
			}
		}
	}
	return result;
}
