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
	document.getElementsByTagName("body")[0].addEventListener("keydown", function(event) {
		if (event.code == "Escape" && !event.shiftKey && !event.ctrlKey && !event.altKey) {
			let cbtn = document.querySelector(".close-btn.active");
			if (cbtn) {
				cbtn.click();
				event.preventDefault();
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

	window.addEventListener("popstate", function(event) {
		let m = Router._url2module();
		if (m) {
			let p = m.pointer;
			if (p && p.onpopstate) {
				p.onpopstate(event.state);
			}
			else {
				Router.go();
			}
			if (p && p.title) {
				Router.update_title(p.title());
			}
		}
	});

	document.getElementById("main-menu").addEventListener("click", function(event) {
		let el = event.target.closest("ul>li");
		if (el) {
			el.classList.toggle("closed");
		}
	});

	Router.go();
};

Router.go = function(url) {
	Status.instance().update({ settings: [ "ui.datetime.offset" ] }).then(function(d) {
		if (d) {
			Router._update_menu(d.authenticated);
			if (d.error_code !== -2) {
				let module = Router._url2module(url);
				if (module) {
					if (!module.pointer)
						module.start(module);
					let p = module.pointer;
					if (p.display)
						p.display();
					if (p.update)
						p.update();
					if (p.title)
						Router.update_title(p.title());
				}
			}
			if (d.state && d.state !== "Ok" && !d.error_code) {
				Notification.add({ type: "warn", text: d.message, delay: 20000 });
			}
			if (d.version !== Router._app_ver) {
				Router._app_ver = d.version;
				Router.update_title();
			}
			if (d.settings && d.settings["ui.datetime.offset"]) {
				Common.tuneDateTimeOutput(d.settings["ui.datetime.offset"]);
			}
		}
	});
};

Router.update_title = function(str) {
	let title1 = "DmarcSrg";
	if (Router._app_ver) {
		title1 += " " + Router._app_ver;
	}
	let title2 = str || Router._title || null;
	if (str) {
		Router._title = str;
	}
	document.title = title1 + (title2 && (": " + title2) || "");
	document.querySelector("h1").childNodes[0].nodeValue = title2 || "";
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
	l_el = document.createElement("li");
	l_el.setAttribute("id", "auth-action");
	let a_el = document.createElement("a");
	a_el.setAttribute("href", "./");
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
					if (resp.status !== 200)
						throw new Error("Failed to log out");
					return resp.json();
				}).then(function(data) {
					if (data.error_code !== undefined && data.error_code !== 0)
						throw new Error(data.message || "Log out: Unknown error");
					Status.instance().reset();
					Router._clear_data();
					Router._update_menu("no");
				}).catch(function(err) {
					console.warn(err.message);
					m_el.classList.remove("disabled");
					Notification.add({ type: "error", text: err.message });
				});
			}
		});
	}
	else if (authenticated == "no") {
		a_el.appendChild(document.createTextNode("Log in"));
		a_el.addEventListener("click", function(event) {
			event.preventDefault();
			LoginDialog.start({ nousername: true });
		});
	}
	l_el.appendChild(a_el);
	m_el.appendChild(l_el);
};

Router._clear_data = function() {
	remove_all_children(document.getElementById("main-block"));
	remove_all_children(document.getElementById("detail-block"));
};

Router._modules = {
	list: {
		start: function(m) {
			m.pointer = new ReportList("main-block");
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
	settings: {
		start: function(m) {
			m.pointer = new Settings();
		}
	}
};

Router._url2module = function(url) {
	let rr = /([^\/]*)$/.exec(url || document.location.pathname);
	return rr && Router._modules[Router._routes[rr[1]]] || null;
};

Router._routes = {
	"": "list",
	"list.php": "list",
	"logs.php": "logs",
	"admin.php": "admin",
	"files.php": "files",
	"report.php": "report",
	"domains.php": "domains",
	"settings.php": "settings"
};

window.onload = Router.start;

