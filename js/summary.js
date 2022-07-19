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
		let format = url_params.get("format");
		if (domain && period) {
			this._options_data = { domain: domain, period: period, format: format || "text" };
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
			url.searchParams.set("format", d.format);
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
		let domain = this._options_data.domain;
		uparams.set("domain", domain);
		uparams.set("period", this._options_data.period);
		uparams.set("format", this._options_data.format === "html" ? "raw" : "text");
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
			report.domain = domain;
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
		let el   = null;
		let text = this._report.text();
		if (text) {
			el = document.createElement("pre");
			el.appendChild(document.createTextNode(this._report.text()));
		} else {
			el = this._report.html();
			if (!el) {
				el = document.createElement("p");
				el.appendChild(document.createTextNode("No data"));
			}
		}
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
			{ name: "days", title: "Days", type: "input" },
			{ name: "format", title: "Format" }
		];
	}

	_gen_content() {
		let container = document.createElement("div");
		container.setAttribute("class", "table");
		this._content.appendChild(container);
		this._ui_data.forEach(function(row) {
			let r_el = this._add_option_row(row.name, row.title, row.type);
			let i_el = r_el.lastChild;
			if (row.name === "days") {
				i_el.setAttribute("type", "number");
				i_el.setAttribute("min", "1");
				i_el.setAttribute("max", "9999");
				i_el.setAttribute("value", "");
			}
			row.element = i_el;
			container.appendChild(r_el);
		}, this);
		this._ui_data[1].element.addEventListener("change", function(event) {
			let days_el = this._ui_data[2].element;
			if (event.target.value === "lastndays") {
				days_el.disabled = false;
				delete days_el.dataset.disabled;
				days_el.value = days_el.dataset.value || "1";
			} else {
				days_el.disabled = true;
				days_el.dataset.value = days_el.value || "1";
				days_el.dataset.disabled = true;
				days_el.value = "";
			}
		}.bind(this));
		this._update_period_element();
		this._update_format_element();

		if (!this._domains) {
			this._fetch_data();
		}
	}

	_submit() {
		let res = {
			domain: this._ui_data[0].element.value,
			period: this._ui_data[1].element.value,
			format: this._ui_data[3].element.value
		};
		if (res.period === "lastndays") {
			res.days = parseInt(this._ui_data[2].element.value) || 1;
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
			let val  = parseInt(c_val[1]);
			let i_el = this._ui_data[2].element;
			i_el.setAttribute("value", val);
			i_el.dataset.value = val;
		}
		el.dispatchEvent(new Event("change"));
	}

	_update_format_element() {
		let el = this._ui_data[3].element;
		let cv = this._data.format || "text";
		[
			[ "text", "Plain text" ],
			[ "html", "HTML" ]
		].forEach(function(it) {
			let opt = document.createElement("option");
			opt.setAttribute("value", it[0]);
			if (it[0] === cv) {
				opt.setAttribute("selected", "");
			}
			opt.appendChild(document.createTextNode(it[1]));
			el.appendChild(opt);
		});
	}

	_enable_ui(enable) {
		let list = this._element.querySelector("form").elements;
		for (let i = 0; i < list.length; ++i) {
			let el = list[1];
			el.disabled = !enable || el.dataset.disabled;
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
	}

	html() {
		let data = this._report.data;
		let html = document.createDocumentFragment();
		let header = document.createElement("h2");
		header.appendChild(document.createTextNode("Domain: " + this._report.domain));
		html.appendChild(header);
		{
			let range = document.createElement("div");
			let d1 = (new Date(data.date_range.begin)).toLocaleDateString();
			let d2 = (new Date(data.date_range.end)).toLocaleDateString();
			range.appendChild(document.createTextNode("Range: " + d1 + " - " + d2));
			html.appendChild(range);
		}
		{
			let header = document.createElement("h3");
			header.appendChild(document.createTextNode("Summary"));
			html.appendChild(header);
			let cont = document.createElement("div");
			cont.setAttribute("class", "left-titled");
			html.appendChild(cont);
			function add_row(title, value, cname) {
				let te = document.createElement("span");
				te.appendChild(document.createTextNode(title + ": "));
				cont.appendChild(te);
				let ve = document.createElement("span");
				if (cname) {
					ve.setAttribute("class", cname);
				}
				ve.appendChild(document.createTextNode(value));
				cont.appendChild(ve);
			}
			let emails = data.summary.emails;
			let total = emails.total;
			add_row("Total", total);
			let aligned = emails.dkim_spf_aligned + emails.dkim_aligned + emails.spf_aligned;
			let n_aligned = total - aligned;
			add_row(
				"DKIM or SPF aligned",
				SummaryReport.num2percent(aligned, total),
				aligned && "report-result-pass" || null
			);
			add_row(
				"Not aligned",
				SummaryReport.num2percent(n_aligned, total),
				n_aligned && "report-result-fail" || null
			);
			add_row("Organizations", data.summary.organizations);
		}
		if (data.sources && data.sources.length) {
			let header = document.createElement("h3");
			header.appendChild(document.createTextNode("Sources"));
			html.appendChild(header);
			let table = document.createElement("table");
			table.setAttribute("class", "report-table");
			html.appendChild(table);

			let caption = document.createElement("caption");
			caption.appendChild(document.createTextNode("Total records: " + data.sources.length));
			table.appendChild(caption);
			let thead = document.createElement("thead");
			table.appendChild(thead);
			[
				[
					[ "IP address", 0, 2 ], [ "Email volume", 0, 2 ], [ "SPF", 3, 0 ], [ "DKIM", 3, 0 ]
				],
				[
					[ "pass" ], [ "fail" ], [ "rate" ], [ "pass" ], [ "fail" ], [ "rate" ]
				]
			].forEach(function(row) {
				let tr = document.createElement("tr");
				thead.appendChild(tr);
				row.forEach(function(col) {
					let th = document.createElement("th");
					th.appendChild(document.createTextNode(col[0]));
					if (col[1]) {
						th.setAttribute("colspan", col[1]);
					}
					if (col[2]) {
						th.setAttribute("rowspan", col[2]);
					}
					tr.appendChild(th);
				});
			});
			let tbody = document.createElement("tbody");
			table.appendChild(tbody);
			data.sources.forEach(function(sou) {
				let tr = document.createElement("tr");
				tbody.appendChild(tr);
				let va = [];
				va.push([ Common.makeIpElement(sou.ip), 0 ]);
				let ett = sou.emails;
				let spf = sou.spf_aligned;
				let dkm = sou.dkim_aligned;
				va.push([ ett, 1 ]);
				va.push([ spf, 3 ]);
				va.push([ ett - spf, 5 ]);
				va.push([ spf / ett, 8 ]);
				va.push([ dkm, 3 ]);
				va.push([ ett - dkm, 5 ]);
				va.push([ dkm / ett, 8 ]);
				va.forEach(function(it) {
					let val  = it[0];
					let mode = it[1];
					let td   = document.createElement("td");
					if (val && (mode & 2)) {
						td.setAttribute("class", "report-result-pass");
					}
					if (val && (mode & 4)) {
						td.setAttribute("class", "report-result-fail");
					}
					if (mode & 8) {
						val = (val * 100).toFixed(0) + "%";
					} else if (mode & 1) {
						val = val.toLocaleString();
					}
					if (typeof(val) === "object") {
						td.appendChild(val);
					} else {
						td.appendChild(document.createTextNode(val));
					}
					tr.appendChild(td);
				});
			});
		}
		if (data.organizations && data.organizations.length) {
			let header = document.createElement("h3");
			header.appendChild(document.createTextNode("Organizations"));
			html.appendChild(header);
			let table = document.createElement("table");
			table.setAttribute("class", "report-table");
			html.appendChild(table);

			let caption = document.createElement("caption");
			caption.appendChild(document.createTextNode("Total records: " + data.organizations.length));
			table.appendChild(caption);
			let thead = document.createElement("thead");
			table.appendChild(thead);
			let tr = document.createElement("tr");
			thead.appendChild(tr);
			[ "Name", "Emails", "Reports" ].forEach(function(org) {
				let th = document.createElement("th");
				th.appendChild(document.createTextNode(org));
				tr.appendChild(th);
			});
			let tbody = document.createElement("tbody");
			table.appendChild(tbody);
			data.organizations.forEach(function(org) {
				let tr = document.createElement("tr");
				tbody.appendChild(tr);
				let va = [];
				va.push(org.name);
				va.push(org.emails.toLocaleString());
				va.push(org.reports.toLocaleString());
				va.forEach(function(v) {
					let td = document.createElement("td");
					td.appendChild(document.createTextNode(v));
					tr.appendChild(td);
				});
			});
		}
		return html;
	}

	static num2percent(per, cent) {
		if (!per) {
			return "0";
		}
		return "" + Math.round(per / cent * 100, per) + "% (" + per + ")";
	}
}

