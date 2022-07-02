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

class Summary {
	constructor(id) {
		this._report = null;
		this._container = null;
		this._options_data = null;
		this._options_block = null;
		this._report_block = null;
	}

	display() {
		let mcn = document.getElementById("main-block");
		remove_all_children(mcn);
		this._create_container();
		mcn.appendChild(this._container);
		this._create_options_block();
		this._create_report_block();
		this._container.appendChild(this._options_block);
		this._container.appendChild(document.createElement("hr"));
		this._container.appendChild(this._report_block);
	}

	update() {
		this._handle_url_params();
		this._update_options_block();
		this._fetch_report();
	}

	title() {
		return "Summary Reports";
	}

	_handle_url_params() {
		let url_params = new URL(document.location.href).searchParams;
		let domain = url_params.get("domain");
		let period = url_params.get("period");
		if (domain && period) {
			this._options_data = { domain: domain, period: period };
		} else {
			this._options_data = null;
		}
	}

	_create_container() {
		this._container = document.createElement("div");
		this._container.setAttribute("class", "panel-container round-border");
	}

	_create_options_block() {
		let opts = document.createElement("div");
		opts.setAttribute("class", "options-block");
		opts.appendChild(document.createTextNode("Report options: "));
		opts.appendChild(document.createTextNode("none"));

		let btn = document.createElement("button");
		btn.setAttribute("class", "options-button");
		btn.appendChild(document.createTextNode("Change"));
		btn.addEventListener("click", function(event) {
			this._display_dialog();
		}.bind(this));
		opts.appendChild(btn);

		this._options_block = opts;
	}

	_update_options_block() {
		let text = "none";
		if (this._options_data) {
			text = "domain=" + this._options_data.domain + " period=" + this._options_data.period;
		}
		this._options_block.childNodes[1].textContent = text;
	}

	_create_report_block() {
		this._report_block = document.createElement("div");
	}

	_display_dialog() {
		let dlg = new OptionsDialog(this._options_data);
		document.getElementById("main-block").appendChild(dlg.element());
		dlg.show().then(function(d) {
			if (!d) {
				return;
			}
			let url = new URL(document.location.href);
			url.searchParams.set("domain", d.domain);
			let period = d.period;
			if (period === "lastndays") {
				period += ":" + d.days;
			}
			url.searchParams.set("period", period);
			window.history.replaceState(null, "", url.toString());
			this.display();
			this.update();
		}.bind(this)).finally(function() {
			this._options_block.lastChild.focus();
		}.bind(this));
	}

	_fetch_report() {
		remove_all_children(this._report_block);
		if (!this._options_data) {
			this._report_block.appendChild(document.createTextNode("Report options are not selected"));
			return;
		}
		this._report_block.appendChild(set_wait_status());
		let uparams = new URLSearchParams();
		uparams.set("domain", this._options_data.domain);
		uparams.set("period", this._options_data.period);
		uparams.set("format", "text");
		window.fetch("summary.php?mode=report&" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok) {
				throw new Error("Failed to fetch the report");
			}
			return resp.json();
		}).then(function(report) {
			if (report.error_code !== undefined && report.error_code !== 0) {
				throw new Error(report.message || "Unknown error");
			}
			this._report = new SummaryReport(report);
			this._display_report();
		}.bind(this)).catch(function(err) {
			console.warn(err.message);
			set_error_status(this._report_block, 'Error: ' + err.message);
		}.bind(this)).finally(function() {
			let wm = this._report_block.querySelector(".wait-message");
			if (wm) {
				wm.remove();
			}
		}.bind(this));
	}

	_display_report() {
		let el = document.createElement("pre");
		el.appendChild(document.createTextNode(this._report.text()));
		this._report_block.appendChild(el);
	}
}

class OptionsDialog extends ModalDialog {
	constructor(params) {
		super({ title: "Report options", buttons: [ "apply", "reset" ] });
		this._data    = params || {};
		this._content = null;
		this._domains = null;
		this._ui_data = [
			{ name: "domain", title: "Domain" },
			{ name: "period", title: "Period" },
		];
		this._days_row = null;
	}

	_gen_content() {
		let container = document.createElement("div");
		container.setAttribute("class", "table");
		this._content.appendChild(container);
		this._ui_data.forEach(function(row) {
			let el = this._add_option_row(row.name, row.title);
			row.element = el.lastChild;
			container.appendChild(el);
		}, this);
		this._ui_data[1].element.addEventListener("change", function(event) {
			let action = event.target.value === "lastndays" ? "remove" : "add";
			this._days_row.classList[action]("hidden");
		}.bind(this));
		{
			let row_el = this._add_option_row("days", "Days", "input");
			container.appendChild(row_el);
			let days = row_el.lastChild;
			days.setAttribute("type", "number");
			days.setAttribute("min", "1");
			days.setAttribute("max", "9999");
			days.setAttribute("value", 1);
			this._days_row = row_el;
		}
		this._update_period_element();
		if (!this._domains) {
			this._fetch_data();
		}
	}

	_submit() {
		let res = {
			domain: this._ui_data[0].element.value,
			period: this._ui_data[1].element.value
		};
		if (res.period === "lastndays") {
			res.days = parseInt(this._days_row.lastChild.value);
		}
		this._result = res;
		this.hide();
	}

	_add_option_row(name, title, type) {
		let r_el = document.createElement("div");
		r_el.setAttribute("class", "row");

		let t_el = document.createElement("span");
		t_el.setAttribute("class", "cell");
		t_el.appendChild(document.createTextNode(title + ": "));
		r_el.appendChild(t_el);

		let n_el = document.createElement(type || "select");
		n_el.setAttribute("name", name);
		n_el.setAttribute("class", "cell");
		r_el.appendChild(n_el);

		return r_el;
	}

	_update_domain_element() {
		let el = this._ui_data[0].element;
		remove_all_children(el);
		let c_val = this._data.domain || "";
		if (this._domains) {
			this._domains.forEach(function(name) {
				let opt = document.createElement("option");
				opt.setAttribute("value", name);
				if (name === c_val) {
					opt.setAttribute("selected", "");
				}
				opt.appendChild(document.createTextNode(name));
				el.appendChild(opt);
			});
		}
	}

	_update_period_element() {
		let el = this._ui_data[1].element;
		let c_val = this._data.period && this._data.period.split(":") || [ "lastweek" ];
		[
			[ "lastweek", "Last week"],
			[ "lastmonth", "Last month" ],
			[ "lastndays", "Last N days" ]
		].forEach(function(it) {
			let opt = document.createElement("option");
			opt.setAttribute("value", it[0]);
			if (it[0] === c_val[0]) {
				opt.setAttribute("selected", "");
			}
			opt.appendChild(document.createTextNode(it[1]));
			el.appendChild(opt);
		});
		if (c_val[1]) {
			this._days_row.lastChild.setAttribute("value", parseInt(c_val[1]));
		}
		el.dispatchEvent(new Event("change"));
	}

	_enable_ui(enable) {
		let list = this._element.querySelector("form").elements;
		for (let i = 0; i < list.length; ++i) {
			list[i].disabled = !enable;
		}
	}

	_fetch_data() {
		this._enable_ui(false);
		this._content.appendChild(set_wait_status());
		window.fetch("summary.php?mode=options", {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok) {
				throw new Error("Failed to fetch the report options list");
			}
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
			this._domains = data.domains;
			this._update_domain_element();
			this._enable_ui(true);
		}.bind(this)).catch(function(err) {
			console.warn(err.message);
			this._content.appendChild(set_error_status());
		}.bind(this)).finally(function() {
			this._content.querySelector(".wait-message").remove();
		}.bind(this));
	}

	_reset() {
		window.setTimeout(function() {
			this._ui_data[1].element.dispatchEvent(new Event("change"));
		}.bind(this), 0);
	}
}

class SummaryReport {
	constructor(data) {
		this._report = data;
	}

	text() {
		let lines = this._report.text ||  [];
		if (lines.length > 0) {
			return lines.join("\n");
		}
		return 'No data';
	}
}

