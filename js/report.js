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

class ReportWidget {
	constructor() {
		this._rep_id = null;
		this._element = null;
		this._close_btn = null;
		this._id_element = null;
		this._cn_element = null;
		this._onclose_act = null;
	}

	display() {
		if (!this._element || !document.contains(this._element)) {
			let cn = document.getElementById("main-block");
			cn.appendChild(this.element());
		}
	}

	update() {
		this.show_report().catch(function(err) {
			Common.displayError(err);
		});
	}

	onpopstate() {
		this.display();
		this.update();
	}

	oncleardata() {
		if (!this._element || !document.contains(this._element)) {
			remove_all_children(document.getElementById("main-block"));
			remove_all_children(document.getElementById("detail-block"));
		}
	}

	show_report(domain, report_id) {
		this.element();
		let that = this;
		return new Promise(function(resolve, reject) {
			if (!domain || !report_id) {
				let sp = (new URL(document.location.href)).searchParams;
				domain = sp.get("domain");
				report_id = sp.get("report_id");
				if (!domain || !report_id) {
					set_error_status(this._cn_element, err.message);
					reject(new Error("Domain and report_id must be specified"));
				}
			}
			that._id_element.childNodes[0].nodeValue = report_id;
			set_wait_status(that._cn_element);
			that._rep_id = report_id;
			that._element.classList.remove("report-hidden");
			that._close_btn.classList.add("active");
			let rep = new Report(domain, report_id);
			rep.fetch().then(function() {
				if (that._rep_id === report_id) {
					remove_all_children(that._cn_element);
					that._cn_element.appendChild(rep.element());
					rep.set_value("seen", true).then(function(data) {
						Common.checkResult(data);
					}).catch(function(err) {
						Common.displayError(err);
					});
				}
				resolve();
			}).catch(function(err) {
				let err_str = rep.error_message() || "Failed to get the report data";
				set_error_status(that._cn_element, err_str);
				reject(err);
			});
		});
	}

	element() {
		if (!this._element) {
			this._gen_element();
		}
		return this._element;
	}

	title() {
		return "Report Detail";
	}

	focus() {
		let el = this._element;
		if (el)
			el.focus();
	}

	hide() {
		if (this._element && !this._element.classList.contains("report-hidden")) {
			this._element.classList.add("report-hidden");
			this._close_btn.classList.remove("active");
			return true;
		}
		return false;
	}

	close() {
		if (this.hide() && this._onclose_act)
			this._onclose_act();
	}

	onclose(fn) {
		this._onclose_act = typeof(fn) == "function" && fn || null;
	}

	_gen_element() {
		let el = document.createElement("div");
		el.setAttribute("class", "report-modal report-hidden");
		el.setAttribute("tabindex", -1);
		el.addEventListener("click", function(event) {
			if (event.target.classList.contains("close-btn") || event.target.classList.contains("report-header")) {
				if (window.history.state && window.history.state.from === "list")
					this.close();
				else
					window.history.go(-1);
			}
		}.bind(this));

		let hd = document.createElement("div");
		hd.setAttribute("class", "report-header");
		{
			let ht = document.createElement("span");
			ht.setAttribute("class", "header-text");
			ht.appendChild(document.createTextNode("DMARC Report (Id: "));

			let id = document.createElement("span");
			id.setAttribute("id", "report-modal-id");
			id.appendChild(document.createTextNode("?"));
			this._id_element = id;
			ht.appendChild(id);

			ht.appendChild(document.createTextNode(")"));
			hd.appendChild(ht);
		}
		el.appendChild(hd);

		let bd = document.createElement("div");
		bd.setAttribute("class", "body");

		let cn = document.createElement("div");
		cn.setAttribute("class", "content");
		this._cn_element = cn;
		bd.appendChild(cn);

		let cb = document.createElement("a");
		cb.setAttribute("class", "btn close-btn");
		cb.appendChild(document.createTextNode("Close"));
		this._close_btn = cb;
		bd.appendChild(cb);

		el.appendChild(bd);

		this._element = el;
	}
}

ReportWidget.instance = function() {
	if (!ReportWidget._instance) {
		ReportWidget._instance = new ReportWidget();
		ReportWidget._instance.onclose(function() {
			window.history.go(-1);
		});
	}
	return ReportWidget._instance;
}

class Report {
	constructor(domain, report_id) {
		this._data = null;
		this._error = false;
		this._error_message = null;
		this._domain = domain;
		this._report_id = report_id;
	}

	id() {
		return this._report_id;
	}

	error() {
		return this._error;
	}

	error_message() {
		return this._error_message;
	}

	fetch() {
		let u_params = new URLSearchParams();
		u_params.set("domain", this._domain);
		u_params.set("report_id", this._report_id);

		let that = this;
		return window.fetch("report.php?" + u_params.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to fetch report data");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			that._data = data.report;
			that._error = false;
			that._error_message = null;
		}).catch(function(err) {
			that._data = null;
			that._error = true;
			that._error_message = err.message;
			throw err;
		});
	}

	element() {
		return this._create_element();
	}

	set_value(name, value) {
		let definitions = {
			"seen": "boolean"
		};

		if (value === undefined || definitions[name] !== typeof(value)) {
			console.warn("Set report value: Incorrect value");
			return Promise.resolve({});
		}

		let url_params = new URLSearchParams();
		url_params.set("action", "set");
		url_params.set("domain", this._domain);
		url_params.set("report_id", this._report_id);
		return window.fetch("report.php?" + url_params.toString(), {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify({ name: name, value: value })
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to set report value");
			return resp.json();
		}).catch(function(err) {
			Common.displayError(err);
		});
	}

	_create_element() {
		let el = document.createDocumentFragment();
		let md = document.createElement("div");
		md.setAttribute("class", "report-metadata");
		md.appendChild(this._create_data_item("Report Id", this._data.report_id));
		md.appendChild(this._create_data_item("Reporting organization", this._data.org_name));
		md.appendChild(this._create_data_item("Domain", this._data.domain));
		let d1 = new Date(this._data.date.begin);
		let d2 = new Date(this._data.date.end);
		md.appendChild(this._create_data_item("Date range", d1.toUIString(true) + " - " + d2.toUIString(true)));
		md.appendChild(this._create_data_item("Email", this._data.email));
		if (this._data.extra_contact_info)
			md.appendChild(this._create_data_item("Extra contact info", this._data.extra_contact_info));
		md.appendChild(this._create_data_item("Published policy", this._create_pub_policy_fragment(this._data.policy)));
		if (this._data.error_string)
			md.appendChild(this._create_data_item("Error string", "???"));
		md.appendChild(this._create_data_item("Loaded time", (new Date(this._data.loaded_time)).toUIString()));
		el.appendChild(md);
		// Records
		let rs = document.createElement("div");
		rs.setAttribute("class", "report-records");
		let hd = document.createElement("h5");
		hd.appendChild(document.createTextNode("Records"));
		rs.appendChild(hd);
		this._data.records.forEach(function(rec) {
			let tl = document.createElement("div");
			tl.setAttribute("class", "report-record round-border");
			let hd = document.createElement("div");
			hd.setAttribute("class", "header");
			hd.appendChild(this._create_data_fragment("IP-address", Common.makeIpElement(rec.ip)));
			tl.appendChild(hd);
			tl.appendChild(this._create_data_item("Message count", rec.count));
			tl.appendChild(this._create_data_item("Policy evaluated", this._create_ev_policy_fragment(rec)));
			if (rec.reason)
				tl.appendChild(this._create_data_item("Evaluated reason", this._create_reason_fragment(rec.reason)));
			tl.appendChild(this._create_data_item("Identifiers", this._create_identifiers_fragment(rec)));
			tl.appendChild(this._create_data_item("DKIM auth", this._create_dkim_auth_fragment(rec.dkim_auth)));
			tl.appendChild(this._create_data_item("SPF auth", this._create_spf_auth_fragment(rec.spf_auth)));
			rs.appendChild(tl);

		}, this);
		el.appendChild(rs);
		return el;
	}

	_create_data_item(title, data) {
		let el = document.createElement("div");
		el.setAttribute("class", "report-item");
		el.appendChild(this._create_data_fragment(title, data));
		return el;
	}

	_create_data_fragment(title, data) {
		let fr = document.createDocumentFragment();
		let tl = document.createElement("span");
		tl.appendChild(document.createTextNode(title + ": "));
		tl.setAttribute("class", "title");
		fr.appendChild(tl);
		let dt = document.createElement("span");
		dt.setAttribute("class", "value");
		if (typeof(data) === "object")
			dt.appendChild(data);
		else
			dt.appendChild(document.createTextNode(data));
		fr.appendChild(dt);
		return fr;
	}

	_create_ev_policy_fragment(data) {
		let fr = document.createDocumentFragment();
		if (data.dkim_align)
			fr.appendChild(create_report_result_element("DKIM", data.dkim_align, true));
		if (data.spf_align)
			fr.appendChild(create_report_result_element("SPF", data.spf_align, true));
		if (data.disposition)
			fr.appendChild(create_report_result_element("disposition", data.disposition, true, ""));
		return fr;
	}

	_create_reason_fragment(data) {
		let fr = document.createDocumentFragment();
		data.forEach(function(rec) {
			let block = document.createElement("div");
			if (rec.type)
				block.appendChild(create_report_result_element("type", rec.type, true, ""));
			if (data[0].comment)
				block.appendChild(create_report_result_element("comment", rec.comment, true, ""));
			fr.appendChild(block);
		});
		return fr;
	}

	_create_identifiers_fragment(data) {
		let fr = document.createDocumentFragment();
		if (data.header_from)
			fr.appendChild(create_report_result_element("header_from", data.header_from, true, ""));
		if (data.envelope_from)
			fr.appendChild(create_report_result_element("envelope_from", data.envelope_from, true, ""));
		if (data.envelope_to)
			fr.appendChild(create_report_result_element("envelope_to", data.envelope_to, true, ""));
		return fr;
	}

	_create_dkim_auth_fragment(data) {
		if (!data)
			return "n/a";
		let fr = document.createDocumentFragment();
		data.forEach(function(rec) {
			let block = document.createElement("div");
			if (rec.domain)
				block.appendChild(create_report_result_element("domain", rec.domain, true, ""));
			if (rec.selector)
				block.appendChild(create_report_result_element("selector", rec.selector, true, ""));
			if (rec.result)
				block.appendChild(create_report_result_element("result", rec.result, true));
			fr.appendChild(block);
		});
		return fr;
	}

	_create_spf_auth_fragment(data) {
		if (!data)
			return "n/a";
		let fr = document.createDocumentFragment();
		data.forEach(function(rec) {
			let block = document.createElement("div");
			if (rec.domain)
				block.appendChild(create_report_result_element("domain", rec.domain, true, ""));
			if (rec.result)
				block.appendChild(create_report_result_element("result", rec.result, true));
			fr.appendChild(block);
		});
		return fr;
	}

	_create_pub_policy_fragment(data) {
		if (!data)
			return "n/a";
		let fr = document.createDocumentFragment();
		if (data.adkim)
			fr.appendChild(create_report_result_element("adkim", data.adkim, true, ""));
		if (data.aspf)
			fr.appendChild(create_report_result_element("aspf", data.aspf, true, ""));
		if (data.p)
			fr.appendChild(create_report_result_element("p", data.p, true, ""));
		if (data.sp)
			fr.appendChild(create_report_result_element("sp", data.sp, true, ""));
		if (data.pct)
			fr.appendChild(create_report_result_element("pct", data.pct, true, ""));
		if (data.fo)
			fr.appendChild(create_report_result_element("fo", data.fo, true, ""));
		return fr;
	}
}

