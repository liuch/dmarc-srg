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
		this._element = document.getElementById("main-block");
		this._container = null;
		this._options_data = null;
		this._options_block = { main: null, domains: null, period: null, button1: null, button2: null };
		this._report_block = null;
	}

	display() {
		this._create_container();
		this._element.appendChild(this._container);
		this._create_options_block();
		this._create_report_block();
		this._container.appendChild(this._options_block.main);
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
			this._options_data = { domains: domain, period: period, format: format || "text" };
			if (period.startsWith("range:")) {
				this._options_data.range = period.substring(6).split("-").map(v => {
					return v.replace(/^(\d{4})(\d{2})(\d{2})$/, "$1-$2-$3");
				});
				this._options_data.period = "range";
			}
		} else {
			this._options_data = null;
		}
	}

	_create_container() {
		this._container = document.createElement("div");
		this._container.setAttribute("class", "panel-container round-border");
	}

	_create_options_block() {
		const main = document.createElement("div");
		main.classList.add("options-block");

		main.appendChild(document.createElement("h2")).textContent = "Report options:";

		const list = main.appendChild(document.createElement("ul"));
		[ "period", "format", "domains" ].forEach(name => {
			const li = list.appendChild(document.createElement("li"));
			li.appendChild(document.createElement("span")).textContent = `${name}: `;
			li.appendChild(document.createElement("span")).textContent = "none";
			this._options_block[name] = li.children[1];
		});

		const bb = main.appendChild(document.createElement("div"));
		bb.classList.add("buttons-block");

		const btn1 = bb.appendChild(document.createElement("button"));
		btn1.classList.add("options-button");
		btn1.textContent = "Change the options";
		btn1.addEventListener("click", event => this._display_dialog());
		this._options_block.button1 = btn1;

		const btn2 = bb.appendChild(document.createElement("button"));
		btn2.classList.add("options-button", "hidden");
		btn2.textContent = "Save CSV data to a file";
		btn2.addEventListener("click", event => {
			const data = Array.from(this._report_block.querySelectorAll("pre")).map(el => el.textContent);
			const blob = new Blob(data, { type: "text/csv" });
			const a = document.createElement("a");
			a.href = URL.createObjectURL(blob);
			a.download = "DMARC summary report.csv";
			a.click();
			setTimeout(() => URL.revokeObjectURL(a.href), 0);
		});
		this._options_block.button2 = btn2;

		this._options_block.main = main;
	}

	_update_options_block() {
		[ "period", "format" ].forEach(id => {
			this._options_block[id].textContent = this._options_data && this._options_data[id] || "none";
		});
		if (this._options_data && this._options_data.period === "range") {
			this._options_block.period.append(` [ ${this._options_data.range.join(" - ")} ]`);
		}
		const de = this._options_block.domains;
		const dl = this._options_data && this._options_data.domains && this._options_data.domains.split(",") || [ "none" ];
		de.replaceChildren(dl.slice(0, 3).join(", "));
		if (dl.length > 3) {
			const dm = document.createElement("a");
			dm.href = "#";
			dm.textContent = `and ${dl.length - 3} more`;
			dm.addEventListener("click", event => {
				event.preventDefault();
				de.replaceChildren(dl.join(", "));
			});
			de.append(" ", dm);
		}
	}

	_create_report_block() {
		this._report_block = document.createElement("div");
		this._report_block.setAttribute("class", "summary-report");
	}

	_display_dialog() {
		let dlg = new OptionsDialog(this._options_data);
		dlg.element().classList.add("report-dialog");
		document.getElementById("main-block").appendChild(dlg.element());
		dlg.show().then(d => {
			if (!d) return;
			const url = new URL(document.location.href);
			url.searchParams.set("domain", d.domain);
			let period = d.period;
			if (period === "lastndays") {
				period += ":" + d.days;
			} else if (period === "range") {
				period += ":" + d.range.map(val => val.replaceAll("-", "")).join("-");
			}
			url.searchParams.set("period", period);
			url.searchParams.set("format", d.format);
			window.history.replaceState(null, "", url.toString());
			remove_all_children(this._element);
			this.display();
			this.update();
		}).finally(() => {
			dlg.element().remove();
			this._options_block.button1.focus();
		});
	}

	_fetch_report() {
		remove_all_children(this._report_block);
		if (!this._options_data) {
			this._report_block.appendChild(document.createTextNode("Report options are not selected"));
			return;
		}
		this._report_block.appendChild(set_wait_status());
		const url = new URL("summary.php", document.location);
		url.searchParams.set("mode", "report");
		url.searchParams.set("domain", this._options_data.domains);
		let period = this._options_data.period;
		if (period === "range") {
			period += ":" + this._options_data.range.map(r => r.replaceAll("-", "")).join("-");
		}
		url.searchParams.set("period", period);
		let format = null;
		switch (this._options_data.format) {
			case "html":
				format = "raw";
				break;
			case "csv":
				format = "csv";
				break;
			default:
				format = "text";
				break;
		}
		url.searchParams.set("format", format);
		window.fetch(url, {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to fetch the report");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._display_report(data.reports.map(rep => new SummaryReport(format, rep)));
		}).catch(err => {
			Common.displayError(err);
			set_error_status(this._report_block, 'Error: ' + err.message);
		}).finally(() => {
			let wm = this._report_block.querySelector(".wait-message");
			if (wm) wm.remove();
		});
	}

	_display_report(reports) {
		function noDataElement() {
			const el = document.createElement("p");
			el.append("No data");
			return el;
		}
		function sepElement(html) {
			if (html) return document.createElement("hr");
			const el = document.createElement("pre");
			el.textContent = "==========\n";
			return el;
		}

		let el = null;
		if (reports.length) {
			el = document.createDocumentFragment();
			reports.forEach((report, index) => {
				switch (report.format) {
					case "raw":
						if (index) el.append(sepElement(true));
						el.append(report.html() || noDataElement());
						break;
					case "text":
						{
							if (index) el.append(sepElement(false));
							const text = report.text();
							if (text) {
								el.appendChild(document.createElement("pre")).textContent = text;
							} else {
								el.append(noDataElement());
							}
						}
						break;
					case "csv":
						el.appendChild(document.createElement("pre")).textContent = report.csv();
						break;
				}
			});
		} else {
			el = noDataElement();
		}
		this._report_block.appendChild(el);
		if (this._options_data && this._options_data.format === "csv") {
			this._options_block.button2.classList.remove("hidden");
		} else {
			this._options_block.button2.classList.add("hidden");
		}
	}
}

class OptionsDialog extends VerticalDialog {
	constructor(params) {
		super({ title: "Report options", buttons: [ "apply", "reset" ] });
		this._data    = params || {};
		this._content = null;
		this._domains = null;
		this._ui_data = [
			{ name: "domains", title: "Domains", type: "multi-select" },
			{ name: "period", title: "Period" },
			{ name: "days", title: "Days", type: "input" },
			{ name: "range", title: "Range", type: "div" },
			{ name: "format", title: "Format" }
		];
	}

	_gen_content() {
		this._ui_data.forEach(row => {
			const i_el = this._insert_input_row(row.title, row.name, row.type);
			const name = row.name;
			if (name === "days") {
				i_el.setAttribute("type", "number");
				i_el.setAttribute("min", "1");
				i_el.setAttribute("max", "9999");
				i_el.setAttribute("value", "");
			} else if (name === "range") {
				const r1 = i_el.appendChild(document.createElement("input"));
				r1.type = "date";
				r1.name = "date1";
				i_el.append(" - ");
				const r2 = i_el.appendChild(document.createElement("input"));
				r2.type = "date";
				r2.name = "date2";
			}
			row.element = i_el;
		});
		this._ui_data[0].element.setAttribute("placeholder", "Pick domains");
		this._ui_data[0].element.addEventListener("change", event => {
			this._buttons[1].disabled = this._ui_data[0].element.isEmpty();
			this._update_first_last();
		});
		this._ui_data[1].element.addEventListener("change", event => {
			const day_el = this._ui_data[2].element;
			const per_el = this._ui_data[3].element;
			const period = event.target.value;
			if (period === "range") {
				day_el.parentElement.classList.add("hidden");
				per_el.parentElement.classList.remove("hidden");
				per_el.querySelectorAll("input").forEach(inp => inp.required = true);
			} else {
				per_el.parentElement.classList.add("hidden");
				day_el.parentElement.classList.remove("hidden");
				per_el.querySelectorAll("input").forEach(inp => inp.required = false);
			}
			if (period === "lastndays") {
				day_el.disabled = false;
				delete day_el.dataset.disabled;
				day_el.value = day_el.dataset.value || "1";
			} else {
				day_el.disabled = true;
				day_el.dataset.value = day_el.value || "1";
				day_el.dataset.disabled = true;
				day_el.value = "";
			}
		});
		this._update_period_element();
		this._update_format_element();

		if (!this._domains) {
			this._fetch_data();
		}
	}

	_insert_input_row(text, name, type) {
		let el = document.createElement(type || "select");
		el.setAttribute("name", name);
		super._insert_input_row(text, el);
		return el;
	}

	_submit() {
		let res = {
			domain: this._ui_data[0].element.getValues().join(","),
			period: this._ui_data[1].element.value,
			format: this._ui_data[4].element.value
		};
		switch (res.period) {
			case "lastndays":
				res.days = parseInt(this._ui_data[2].element.value) || 1;
				break;
			case "range":
				res.range = Array.from(this._ui_data[3].element.querySelectorAll("input")).map(inp => inp.value);
				if (Date.UTC(...res.range[0].split("-")) > Date.UTC(...res.range[1].split("-"))) {
					Notification.add({ text: "Incorrect date range", type: "error" });
					this._ui_data[3].element.querySelector("input").focus();
					return;
				}
				break;
		}
		this._result = res;
		this.hide();
	}

	_reset() {
		this._ui_data[0].element.setValues(this._data.domains && this._data.domains.split(",") || []);
		window.setTimeout(() => this._ui_data[1].element.dispatchEvent(new Event("change")), 0);
	}

	_update_domain_element() {
		let el = this._ui_data[0].element;
		el.clear();
		if (this._domains) {
			this._domains.forEach(name => el.appendItem(name));
		}
		if (this._data.domains) el.setValues(this._data.domains.split(","));
	}

	_update_period_element() {
		let el = this._ui_data[1].element;
		let c_val = this._data.period && this._data.period.split(":") || [ "lastweek" ];
		[
			[ "lastweek", "Last week"],
			[ "lastmonth", "Last month" ],
			[ "lastndays", "Last N days" ],
			[ "range", "Date range"]
		].forEach(it => {
			let opt = document.createElement("option");
			opt.setAttribute("value", it[0]);
			if (it[0] === c_val[0]) {
				opt.setAttribute("selected", "");
			}
			el.appendChild(opt).textContent = it[1];
		});
		if (c_val[1]) {
			let val  = parseInt(c_val[1]);
			let i_el = this._ui_data[2].element;
			i_el.setAttribute("value", val);
			i_el.dataset.value = val;
		}
		if (this._data.range) {
			this._ui_data[3].element.querySelectorAll("input").forEach((el, idx) => {
				let rv = this._data.range[idx];
				if (rv) el.setAttribute("value", rv);
			});
		}
		el.dispatchEvent(new Event("change"));
	}

	_update_format_element() {
		let el = this._ui_data[4].element;
		let cv = this._data.format || "text";
		[
			[ "text", "Plain text" ],
			[ "html", "HTML" ],
			[ "csv", "CSV data" ]
		].forEach(it => {
			let opt = document.createElement("option");
			opt.setAttribute("value", it[0]);
			if (it[0] === cv) {
				opt.setAttribute("selected", "");
			}
			el.appendChild(opt).textContent = it[1];
		});
	}

	_enable_ui(enable) {
		const controls = Array.from(this._element.querySelector("form").elements);
		controls.push(this._ui_data[0].element);
		for (const el of controls) {
			el.disabled = !enable || el.dataset.disabled;
		}
		this._update_first_last();
		if (this._first) this._first.focus();
	}

	_fetch_data() {
		this._enable_ui(false);
		this._content.appendChild(set_wait_status());
		window.fetch("summary.php?mode=options", {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to fetch the report options list");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._domains = data.domains;
			this._update_domain_element();
			this._enable_ui(true);
		}).catch(err => {
			Common.displayError(err);
			this._content.appendChild(set_error_status());
		}).finally(() => {
			this._content.querySelector(".wait-message").remove();
		});
	}
}

class SummaryReport {
	constructor(format, data) {
		this.format  = format;
		this._report = data;
	}

	text() {
		let lines = this._report.text ||  [];
		if (lines.length > 0) {
			return lines.join("\n");
		}
	}

	csv() {
		return this._report.csv || "";
	}

	html() {
		const data = this._report.data;
		const html = document.createDocumentFragment();

		html.appendChild(document.createElement("h2")).textContent = "Domain: " + this._report.domain;
		html.appendChild(document.createElement("div")).append(
			"Range: ", (new Date(data.date_range.begin)).toUIDateString(true),
			" - ", (new Date(data.date_range.end)).toUIDateString(true)
		);

		{
			html.appendChild(document.createElement("h3")).textContent = "Summary";
			const cont = html.appendChild(document.createElement("div"));
			cont.classList.add("left-titled");
			const emails = data.summary.emails;
			const total = emails.total;
			cont.append(SummaryReport.makeSummaryRow("Total", total, null, null));
			const f_aligned = emails.dkim_spf_aligned;
			const p_aligned = emails.dkim_aligned + emails.spf_aligned;
			const n_aligned = total - f_aligned - p_aligned;
			const rejected = emails.rejected;
			const quarantined = emails.quarantined;
			[
				[ "Fully aligned", f_aligned, "pass" ], [ "Partial aligned", p_aligned, null ],
				[ "Not aligned", n_aligned, "fail" ], [ "Quarantined", quarantined, "fail" ],
				[ "Rejected", rejected, "fail" ]
			].forEach(it => cont.append(SummaryReport.makeSummaryRow(it[0], it[1], total, it[2])));
		}
		if (data.sources && data.sources.length) {
			html.appendChild(document.createElement("h3")).textContent = "Sources";
			const table = html.appendChild(document.createElement("table"));
			table.classList.add("report-table");
			table.appendChild(document.createElement("caption")).textContent = "Total records: " + data.sources.length;
			const thead = document.createElement("thead");
			table.appendChild(thead);
			thead.append(SummaryReport.makeHeaderRow(
				[ [ "IP address", 0, 2 ], [ "Email volume", 0, 2 ], [ "Partial aligned", 2, 0 ], [ "Not aligned", 0, 2 ], [ "Disposition", 2, 0 ] ]
			));
			thead.append(SummaryReport.makeHeaderRow(
				[ [ "SPF only" ], [ "DKIM only" ], [ "quar+rej" ], [ "fail rate" ] ]
			));
			const tbody = table.appendChild(document.createElement("tbody"));
			data.sources.forEach(sou => {
				const tr = tbody.appendChild(document.createElement("tr"));
				[ Common.makeIpElement(sou.ip), sou.emails, sou.spf_aligned, sou.dkim_aligned ].forEach(
					v => tr.append(SummaryReport.makeResultElement("td", v, null))
				);
				const dq = sou.quarantined;
				const dr = sou.rejected;
				const ds = dq || dr ? `${dq.toLocaleString()}+${dr.toLocaleString()}` : 0;
				[
					sou.emails - sou.dkim_aligned - sou.spf_aligned - sou.dkim_spf_aligned, ds
				].forEach(v => tr.append(SummaryReport.makeResultElement("td", v, "fail")));
				tr.append(SummaryReport.makeResultElement("td", SummaryReport.num2percent(dq + dr, sou.emails, false)));
			});
		}
		if (data.organizations && data.organizations.length) {
			html.appendChild(document.createElement("h3")).textContent = "Reporting organizations";
			const table = html.appendChild(document.createElement("table"));
			table.classList.add("report-table");

			table.appendChild(document.createElement("caption")).textContent = "Total records: " + data.organizations.length;
			const thead = table.appendChild(document.createElement("thead"));
			thead.append(SummaryReport.makeHeaderRow(
				[ [ "Name", 0, 2 ], [ "Volume", 2, 0 ], [ "Partial aligned", 2, 0 ], [ "Not aligned", 0, 2 ], [ "Disposition", 2, 0 ] ]
			));
			thead.append(SummaryReport.makeHeaderRow(
				[ [ "reports" ], [ "emails" ], [ "SPF only" ], [ "DKIM only" ], [ "quar+rej" ], [ "fail rate" ] ]
			));
			const tbody = table.appendChild(document.createElement("tbody"));
			data.organizations.forEach(org => {
				const tr = tbody.appendChild(document.createElement("tr"));
				[
					org.name, org.reports, org.emails, org.spf_aligned, org.dkim_aligned
				].forEach(v => tr.append(SummaryReport.makeResultElement("td", v)));
				const dq = org.quarantined;
				const dr = org.rejected;
				const ds = dq || dr ? `${dq.toLocaleString()}+${dr.toLocaleString()}` : 0;
				[
					org.emails - org.dkim_aligned - org.spf_aligned - org.dkim_spf_aligned, ds
				].forEach(v => tr.append(SummaryReport.makeResultElement("td", v, "fail")));
				tr.append(SummaryReport.makeResultElement("td", SummaryReport.num2percent(dq + dr, org.emails, false)));
			});
		}
		return html;
	}

	static num2percent(per, cent, with_num) {
		if (!per) return 0;
		let res = "" + Math.round(per / cent * 100) + "%";
		if (with_num) res += " (" + per + ")";
		return res;
	}

	static makeSummaryRow(title, value, total, type) {
		const re = document.createDocumentFragment();
		const te = re.appendChild(document.createElement("span"));
		te.textContent = title + ": ";
		if (total) {
			re.append(SummaryReport.makeResultElement("span", SummaryReport.num2percent(value, total, true), type));
		} else {
			re.append(value);
		}
		return re;
	}

	static makeHeaderRow(data) {
		const tr = document.createElement("tr");
		data.forEach(row => {
			const td = tr.appendChild(document.createElement("th"));
			if (row[1]) td.colSpan = row[1];
			if (row[2]) td.rowSpan = row[2];
			td.textContent = row[0];
		});
		return tr;
	}

	static makeResultElement(name, value, type) {
		const el = document.createElement(name);
		if (value && type) el.classList.add("report-result-" + type);
		el.append(typeof(value) === "number" ? value.toLocaleString() : value);
		return el;
	}
}
