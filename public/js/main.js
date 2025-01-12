/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020 Aleksey Andreev (liuch)
 *
 * Available at:
 * https://github.com/liuch/dmarc-srg
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of  MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class Router {
}

Router.start = function() {
	Router._initial_header = document.querySelector("h1").textContent;

	document.getElementsByTagName("body")[0].addEventListener("keydown", function(event) {
		if (event.defaultPrevented) return;
		if (event.code == "Escape" && !event.shiftKey && !event.ctrlKey && !event.altKey) {
			let cbs = document.querySelectorAll(".close-btn.active");
			for (let i = cbs.length - 1; i >= 0; --i) {
				if (window.getComputedStyle(cbs[i]).display !== "none") {
					cbs[i].click();
					event.preventDefault();
					break;
				}
			}
			document.querySelectorAll("div.popup-menu:not(.hidden)").forEach(function(m) {
				m.classList.add("hidden");
			});
		}
	});

	window.addEventListener("click", function(event) {
		if (!event.target.closest("div.popup-menu")) {
			document.querySelectorAll("div.popup-menu:not(.hidden)").forEach(function(m) {
				m.classList.add("hidden");
			});
		}
	});

	document.getElementById("main-menu-block").addEventListener("click", function(event) {
		let el = event.target;
		if (el.tagName === "A") {
			let href = el.getAttribute("href");
			if (href !== "" && href !== "#") {
				event.preventDefault();
				window.history.pushState(null, "", href);
				Router.go();
				MenuBar.instance().updateCurrent();
			}
		}
	});

	window.addEventListener("popstate", function(event) {
		let m = Router._url2module();
		if (m) {
			let p = m.pointer;
			if (p && p.onpopstate) {
				if (p.title) {
					Router.update_title(p.title());
				}
				p.onpopstate(event.state);
			} else {
				Router.go();
				MenuBar.instance().updateCurrent();
			}
		}
	});

	document.querySelector(".menu-box .about a").addEventListener("click", function(event) {
		event.preventDefault();
		setTimeout(function() {
			let dlg = new AboutDialog({
				authors: [
					{ name:  "Aleksey Andreev", url:   "https://github.com/liuch", years: "2021-2024" }
				],
				documentation: [
					{ ancor: "README on GitHub", url: "https://github.com/liuch/dmarc-srg/blob/master/README.md" }
				],
				source_code: [
					{ ancor: "DmarcSrg on GitHub", url: "https://github.com/liuch/dmarc-srg" }
				]
			});
			document.getElementById("main-block").appendChild(dlg.element());
			dlg.show().finally(function() {
				dlg.element().remove();
			});
		}, 0);
	});

	const menu = MenuBar.instance().init();
	Router.go();
	menu.updateCurrent();
};

Router.go = function(url) {
	Status.instance().update({
		page: this._page_name(url),
		settings: [ "ui.datetime.offset", "ui.ipv4.url", "ui.ipv6.url", "report-view.filter.initial-value" ]
	}).then(function(d) {
		if (d) {
			Router._update_menu(d.authenticated);
			Router._update_user();
			if (d.settings) {
				if (d.settings["ui.datetime.offset"]) {
					Common.tuneDateTimeOutput(d.settings["ui.datetime.offset"]);
				}
				Common.ipv4_url = d.settings["ui.ipv4.url"] || '';
				Common.ipv6_url = d.settings["ui.ipv6.url"] || '';
				Common.rv_filter = d.settings["report-view.filter.initial-value"] || null;
			}
			if (d.error_code !== -2) {
				try {
					Common.checkResult(d);
				} catch (err) {
					Common.displayError(err);
				}
				let module = Router._url2module(url);
				if (module) {
					if (!module.pointer)
						module.start(module);
					let p = module.pointer;
					if (p.oncleardata)
						p.oncleardata();
					else
						Router._clear_data();
					if (p.title)
						Router.update_title(p.title());
					if (p.display)
						p.display();
					if (p.update)
						p.update();
				}
			}
			if (d.state && d.state !== "Ok" && !d.error_code && d.message) {
				Notification.add({ type: "warn", text: d.message, delay: 20000, name: "st-err" });
			}
			if (d.version !== Router._app_ver) {
				Router._app_ver = d.version;
				Router.update_title();
			}
			if (d.php_version) {
				Router.php_version = d.php_version;
			}
		}
	});
};

Router.app_name = function(version) {
	let name = "DmarcSrg";
	if (version && Router._app_ver) {
		name += " " + Router._app_ver;
	}
	return name;
}

Router.update_title = function(str) {
	let title1 = Router.app_name(false);
	let title2 = str || Router._title || null;
	if (str) {
		Router._title = str;
	}
	document.title = title1 + (title2 && (": " + title2) || "");
	let h1 = document.querySelector("h1");
	if (str === "") {
		h1.textContent = Router._initial_header || "";
	} else if (str) {
		h1.textContent = title2 || "";
	}
};

Router._update_menu = function(authenticated) {
	let aa = document.getElementById("auth-action");
	if (aa) aa.remove();

	if (User && User.auth_type !== "base") {
		MenuBar.instance().element(".users").classList.add("hidden");
	}

	if (authenticated === "yes") {
		aa = MenuBar.instance().insertItem("Log out", "#", -1);
		aa.id = "auth-action";
		aa.addEventListener("click", event => {
			event.preventDefault();
			if (!aa.classList.contains("disabled")) {
				aa.classList.add("disabled");
				window.fetch("logout.php", {
					method: "POST",
					cache: "no-store",
					headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
					credentials: "same-origin",
					body: JSON.stringify({})
				}).then(resp => {
					if (!resp.ok) throw new Error("Failed to log out");
					return resp.json();
				}).then(data => {
					Common.checkResult(data);
					User.name = null;
					User.level = null;
					Status.instance().reset();
					Router._clear_data();
					Router._update_menu("no");
					Router._update_user();
					Router.update_title("");
				}).catch(err => {
					Common.displayError(err);
					aa.classList.remove("disabled");
					Notification.add({ type: "error", text: err.message, name: "auth" });
				});
			}
		});
	} else if (authenticated === "no") {
		aa = MenuBar.instance().insertItem("Log in", "#", -1);
		aa.id = "auth-action";
		aa.addEventListener("click", event => {
			event.preventDefault();
			LoginDialog.start();
		});
	}
};

Router._update_user = function() {
	const levels = new Map([ [ "admin", false ], [ "manager", false ] ]);
	switch (User.level) {
		case null:
		case "admin":
			levels.set("admin", true);
			//no break;
		case "manager":
			levels.set("manager", true);
			break;
	}
	levels.forEach(function(value, key) {
		document.body.classList[value ? "add" : "remove"]("level-" + key);
	});
}

Router._clear_data = function() {
	document.getElementById("main-block").replaceChildren();
	document.getElementById("detail-block").replaceChildren();
};

Router._modules = {
	list: {
		start: function(m) {
			m.pointer = new ReportList();
		}
	},
	report: {
		start: function(m) {
			m.pointer = ReportWidget.instance();
		}
	},
	admin: {
		start: function(m) {
			m.pointer = new Admin();
		}
	},
	files: {
		start: function(m) {
			m.pointer = new Files();
		}
	},
	users: {
		start: function(m) {
			m.pointer = new UserList();
		}
	},
	domains: {
		start: function(m) {
			m.pointer = new DomainList();
		}
	},
	logs: {
		start: function(m) {
			m.pointer = new Logs();
		}
	},
	summary: {
		start: function(m) {
			m.pointer = new Summary();
		}
	},
	settings: {
		start: function(m) {
			m.pointer = new Settings();
		}
	}
};

Router._page_name = function(url) {
	const r = /([^\/]*)$/.exec(url || document.location.pathname);
	return r && this._routes[r[1]] || null;
}

Router._url2module = function(url) {
	const pname = this._page_name(url);
	return pname && this._modules[pname] || null;
};

Router._routes = {
	"": "list",
	"list.php": "list",
	"logs.php": "logs",
	"admin.php": "admin",
	"users.php": "users",
	"files.php": "files",
	"report.php": "report",
	"domains.php": "domains",
	"summary.php": "summary",
	"settings.php": "settings"
};

window.onload = Router.start;
