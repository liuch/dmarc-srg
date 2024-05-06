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
		let mm_toggle = document.getElementById("main-menu-toggle");
		if (mm_toggle.checked) {
			if (event.target.tagName == "A" || !event.target.closest("#main-menu-button")) {
				mm_toggle.checked = false;
			}
		}
	});

	document.getElementById("main-menu-button").addEventListener("click", function(event) {
		let el = event.target;
		if (el.tagName === "A") {
			let href = el.getAttribute("href");
			if (href !== "") {
				event.preventDefault();
				window.history.pushState(null, "", href);
				Router.go();
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
			}
		}
	});

	document.getElementById("main-menu").addEventListener("click", function(event) {
		let el = event.target.closest("ul>li");
		if (el) {
			el.classList.toggle("closed");
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

	Router.go();
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
	let m_el = document.getElementById("main-menu");
	let l_el = m_el.querySelector("#auth-action");
	if (l_el) {
		l_el.remove();
	}
	{
		let subs = m_el.querySelectorAll(".submenu .selected")
		for (let i = 0; i < subs.length; ++i) {
			subs[i].classList.remove("selected");
		}
		let href = document.location.origin + document.location.pathname;
		let f1 = false;
		for (let i = 0; i < m_el.children.length; ++i) {
			let smenu = m_el.children[i];
			if (smenu !== l_el) {
				let f2 = false;
				if (!f1) {
					let a_ls = smenu.querySelectorAll("ul>li>a");
					for (let k = 0; k < a_ls.length; ++k) {
						let a = a_ls[k];
						if (a.href === href) {
							f1 = true;
							f2 = true;
							a.parentElement.classList.add("selected")
							break;
						}
					}
				}
				if (f2) {
					smenu.classList.remove("closed");
				}
				else {
					smenu.classList.add("closed");
				}
			}
		}
	}
	if (User && User.auth_type !== "base") {
		m_el.querySelector(".users").classList.add("hidden");
	}
	if (authenticated !== "disabled") {
		l_el = document.createElement("li");
		l_el.setAttribute("id", "auth-action");
		let a_el = document.createElement("a");
		a_el.setAttribute("href", "");
		if (authenticated == "yes") {
			a_el.appendChild(document.createTextNode("Log out"));
			a_el.addEventListener("click", function(event) {
				event.preventDefault();
				if (!this.classList.contains("disabled")) {
					let m_el = this;
					m_el.classList.add("disabled");
					window.fetch("logout.php", {
						method: "POST",
						cache: "no-store",
						headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
						credentials: "same-origin",
						body: JSON.stringify({})
					}).then(function(resp) {
						if (!resp.ok)
							throw new Error("Failed to log out");
						return resp.json();
					}).then(function(data) {
						Common.checkResult(data);
						User.name = null;
						User.level = null;
						Status.instance().reset();
						Router._clear_data();
						Router._update_menu("no");
						Router._update_user();
						Router.update_title("");
					}).catch(function(err) {
						Common.displayError(err);
						m_el.classList.remove("disabled");
						Notification.add({ type: "error", text: err.message, name: "auth" });
					});
				}
			});
		}
		else if (authenticated == "no") {
			a_el.appendChild(document.createTextNode("Log in"));
			a_el.addEventListener("click", function(event) {
				event.preventDefault();
				LoginDialog.start();
			});
		}
		l_el.appendChild(a_el);
		m_el.appendChild(l_el);
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
	remove_all_children(document.getElementById("main-block"));
	remove_all_children(document.getElementById("detail-block"));
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
