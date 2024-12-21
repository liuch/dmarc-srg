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

class ReportList {
	constructor() {
		this._page = null;
		this._table = null;
		this._scroll = null;
		this._filter = null;
		this._sort = { column: "begin_time", direction: "descent" };
		this._element = document.getElementById("main-block");
		this._element2 = document.getElementById("detail-block");
		this._fetching = false;
		this._filter_btn = null;
		this._rep_counter = null;
		this._cnt_updated = 0;
		this._settings_dlg = null;
		this._column_list = [
			{ name: "domain", content: "Domain", class: "fqdn" },
			{ name: "begin_time", content: "Date", sortable: true },
			{ name: "organization", content: "Reporting Organization", class: "orgname" },
			{ name: "report_id", content: "Report ID", class: "report-id" },
			{ name: "messages", content: "Messages" },
			{ name: "result", content: "Result" },
			{ name: "disposition", content: "Disposition" },
			{ name: "quarantined", content: "Quarantined" },
			{ name: "rejected", content: "Rejected" }
		];
	}

	display() {
		this._make_page_container();
		this._make_scroll_container();
		this._make_table();
		const t_el = this._table.element();
		const b_el = this._make_toolbar().element();
		b_el.setAriaControls(t_el);
		this._scroll.append(t_el);
		this._page.append(b_el, this._scroll);
		this._element.append(this._page);
		this._ensure_report_widget();
		this._element2.appendChild(ReportWidget.instance().element());
		ReportWidget.instance().hide();
		this._table.focus();
	}

	update() {
		this._filter = Common.getFilterFromURL(new URL(document.location));
		this._update_table();
		this._update_settings_button();
	}

	title() {
		return "Report List";
	}

	onpopstate() {
		if (!this._page) {
			this.display();
			this.update();
		}
		else {
			if (!this._element.contains(this._page)) this._element.replaceChildren(this._page);
			const f = Common.getFilterFromURL(new URL(document.location), this._filter);
			if (f !== undefined) {
				this._filter = f;
				Status.instance().reset();
				Status.instance().update({ page: "list" });
				this._update_table();
				this._update_settings_button();
			}
		}
		this._ensure_report_widget();
		if (this._table) {
			this._table.focus();
		}
	}

	_ensure_report_widget() {
		let wdg = ReportWidget.instance();
		wdg.hide();
		let el = wdg.element();
		if (!this._element2.contains(el)) {
			this._element2.appendChild(el);
		}
	}

	_update_settings_button() {
		if (this._filter_btn) {
			this._filter_btn.element().classList[ this._filter ? "add" : "remove" ]("active");
		}
	}

	_make_page_container() {
		const el = document.createElement("div");
		el.classList.add("page-container");
		this._page = el;
	}

	_make_scroll_container() {
		const el = document.createElement("div");
		el.tabIndex = -1;
		el.classList.add("table-wrapper");
		el.addEventListener("scroll", event => {
			if (!this._fetching && el.scrollTop + el.clientHeight >= el.scrollHeight * 0.95) {
				if (this._table.frames_count() === 0 || this._table.more()) {
					this._fetch_list();
				}
			}
		});
		this._scroll = el;
	}

	_make_toolbar() {
		const tb = new Toolbar("Report list toolbar");
		this._filter_btn = new ToolbarButton({
			title:   "Filter",
			content: "filter_icon",
			onclick: () => this._display_settings_dialog()
		});
		const cb = new ToolbarButton({
			title:   "Columns",
			content: "columns_icon",
			onclick: () => this._display_columns_dialog()
		});
		this._rep_counter = new ReportCounter();
		tb.appendItem(this._filter_btn).appendItem(cb).appendSpacer().appendItem(this._rep_counter.element());
		return tb;
	}

	_make_table() {
		this._table = new ReportTable({
			class:   "main-table report-list small-cards",
			onclick: function(row) {
				let data = row.userdata();
				if (data)
					this._display_report(data, row.id());
			}.bind(this),
			onsort: function(col) {
				let dir = col.sorted() && "toggle" || "descent";
				this._table.set_sorted(col.name(), dir);
				this._sort.column = col.name();
				this._sort.direction = col.sorted();
				this.update();
			}.bind(this),
			onfocus: function(el) {
				scroll_to_element(el, this._scroll);
			}.bind(this)
		});
		this._column_list.forEach(col => {
			let c = this._table.add_column(col);
			if (c.name() === this._sort.column) {
				c.sort(this._sort.direction);
			}
		});
		this._get_visible_columns(true);
	}

	_update_table() {
		this._table.clear();
		let that = this;
		let frcnt = -1;
		let again = function() {
			if (frcnt < that._table.frames_count() && that._scroll.clientHeight * 1.5 >= that._scroll.scrollHeight) {
				frcnt = that._table.frames_count();
				that._fetch_list().then(function(frame) {
					if (frame && frame.more())
						again();
					else
						that._table.focus();
				});
			}
			else
				that._table.focus();
		}
		again();
	}

	_display_report(data, id) {
		if (data.domain && data.time && data.org && data.report_id) {
			let url = new URL("report.php", document.location);
			url.searchParams.set("org", data.org);
			url.searchParams.set("time", data.time);
			url.searchParams.set("domain", data.domain);
			url.searchParams.set("report_id", data.report_id);
			window.history.pushState({ from: "list" }, "", url);
			let filter = this._filter && {
				dkim: this._filter.dkim || "",
				spf: this._filter.spf || "",
				disposition: this._filter.disposition || ""
			} || null;
			ReportWidget.instance().show_report(data.domain, data.time, data.org, data.report_id, filter).then(() => {
				if (!this._table.seen(id)) {
					this._table.seen(id, true);
					this._rep_counter.decrease();
				}
			}).catch(err => {
				Common.displayError(err);
				if (err.error_code && err.error_code === -2) {
					LoginDialog.start();
				}
			});
			Router.update_title(ReportWidget.instance().title());
			ReportWidget.instance().focus();
		}
	}

	_fetch_list() {
		this._table.display_status("wait");
		this._fetching = true;
		const qlist = [ "reports" ];
		const pos = this._table.last_row_index() + 1;
		const now =  Date.now();
		if (!pos  || now - this._cnt_updated >= 60000) {
			qlist.push("count");
			this._cnt_updated = now;
		}

		const uparams = new URLSearchParams();
		uparams.set("list", qlist.join(","));
		uparams.set("position", pos);
		uparams.set("order", this._sort.column);
		uparams.set("direction", this._sort.direction);
		if (this._filter) {
			for (let nm in this._filter) {
				uparams.append("filter[]", nm + ":" + this._filter[nm]);
			}
		}

		return window.fetch("list.php?" + uparams, {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok)
				throw new Error("Failed to fetch the report list");
			return resp.json();
		}).then(data => {
			this._table.display_status(null);
			Common.checkResult(data);
			let d = { more: data.more };
			d.rows = data.reports.map(it => {
				return new ReportTableRow(this._make_row_data(it));
			});
			let fr = new ITableFrame(d, pos);
			this._table.add_frame(fr);
			if (data.count) this._rep_counter.set(data.count);
			return fr;
		}).catch(err => {
			Common.displayError(err);
			this._table.display_status("error");
		}).finally(() => {
			this._fetching = false;
		});
	}

	_make_row_data(d) {
		let rd = {
			cells: [],
			userdata: { domain: d.domain, time: d.date.begin, org: d.org_name, report_id: d.report_id },
			seen: d.seen && true || false
		};
		rd.cells.push({ content: d.domain, label: "Domain", class: "fqdn" });
		let d1 = new Date(d.date.begin);
		let d2 = new Date(d.date.end);
		rd.cells.push({
			content: date_range_to_string(d1, d2),
			title: d1.toUIString(true) + " - " + d2.toUIString(true),
			label: "Date"
		});
		rd.cells.push({ content: d.org_name, label: "Reporting Organization", class: "orgname" });
		rd.cells.push({ content: d.report_id, label: "Report ID", class: "report-id" });
		rd.cells.push({ content: Common.abbrNumber(d.messages, 1e6), label: "Messages" });
		rd.cells.push(new ResultColumn({ dkim_align: d.dkim_align, spf_align: d.spf_align }, { label: "Result" }));
		rd.cells.push(new DispositionColumn({
			none: d.messages - d.rejected - d.quarantined,
			rejected: d.rejected,
			quarantined: d.quarantined
		}, { label: "Disposition" }));
		rd.cells.push(new FailsColumn(d.quarantined, { label: "Quarantined" }));
		rd.cells.push(new FailsColumn(d.rejected, { label: "Rejected" }));
		return rd;
	}

	_display_settings_dialog() {
		let dlg = this._settings_dlg;
		if (!this._settings_dlg) {
			dlg = new ReportListFilterDialog({ filter:  this._filter });
			this._settings_dlg = dlg;
		}
		this._element.append(dlg.element());
		dlg.show().then(d => {
			if (!d) return;
			const url = new URL(document.location);
			url.searchParams.delete("filter[]");
			for (const k in d) {
				if (d[k]) url.searchParams.append("filter[]", `${k}:${d[k]}`);
			}
			window.history.replaceState(null, "", url);
			const f = Common.getFilterFromURL(url, this._filter);
			if (f !== undefined) {
				this._filter = f;
				Status.instance().reset();
				Status.instance().update({ page: "list" });
				this._update_table();
				this._update_settings_button();
			}

		}).finally(() => {
			this._table.focus();
		});
	}

	_get_visible_columns(update) {
		let names = null;
		try {
			names = JSON.parse(window.localStorage.getItem("reportListColumns"));
		} catch (err) {
		}
		let res = null;
		if (Array.isArray(names)) {
			res = names.filter(name => this._column_list.find(c => (c.name === name)));
		}
		if (!res || !res.length) res = [ "domain", "begin_time", "organization", "messages", "result", "disposition" ];
		if (update) {
			this._table.set_columns_visible(this._column_list.reduce((list, col, idx) => {
				if (res.includes(col.name)) list.push(idx);
				return list;
			}, []));
		}
		return res;
	}

	_save_visible_columns (columns) {
		try {
			window.localStorage.setItem("reportListColumns", JSON.stringify(columns));
		} catch (err) {
			Notification.add({ text: "Unable to save the column set", type: "error" });
		}
	}

	_display_columns_dialog() {
		const dlg = new ReportListColumnsDialog({ columns: this._column_list, checked: this._get_visible_columns(false) });
		this._element.append(dlg.element())
		dlg.show().then(d => {
			if (!d) return;
			this._save_visible_columns(d);
			this._get_visible_columns(true);
		}).finally(() => {
			dlg.element().remove();
			this._table.focus();
		});
	}
}

class ReportCounter {
	constructor() {
		this._total = null;
		this._unread = null;
		this._element = null;
		this._total_el = null;
		this._unread_el = null;
	}

	set(data) {
		this._total = data.total;
		this._unread = data.unread;
		if (this._element) this._update_element();
	}

	decrease() {
		--this._unread;
		if (this._element) this._update_element();
	}

	element() {
		if (!this._element) {
			this._element = document.createElement("span");
			this._total_el = document.createElement("strong");
			this._unread_el = document.createElement("strong");
			this._update_element();
			this._element.append("Unread: ", this._unread_el, " of ", this._total_el);
		}
		return this._element;
	}

	_update_element() {
		this._total_el.textContent = this._total !== null ? this._total : '-';
		this._unread_el.textContent = this._unread !== null ? this._unread : '-';
	}
}

class ReportTable extends ITable {
	seen(row_id, flag) {
		let row = super._get_row(row_id);
		if (row) {
			if (flag === undefined)
				return row.seen();
			row.seen(flag);
		}
	}
}

class ReportTableRow extends ITableRow {
	constructor(data) {
		super(data);
		this._seen = data.seen && true || false;
	}

	element() {
		if (!this._element) {
			super.element();
			this._update_seen_element();
		}
		return this._element;
	}

	seen(flag) {
		if (flag === undefined)
			return this._seen;

		this._seen = flag && true || false;
		if (this._element)
			this._update_seen_element();
	}

	_update_seen_element() {
		if (this._seen)
			this._element.classList.remove("unseen");
		else
			this._element.classList.add("unseen");
	}
}

class ResultColumn extends ITableCell {
	value(target) {
		if (target === "dom") {
			let d = this._content;
			let fr = document.createDocumentFragment();
			[ [ "dkim_align", "DKIM" ], [ "spf_align", "SPF" ] ].forEach(ai => {
				const align = d[ai[0]];
				if (align) {
					if (![ "fail", "unknown" ].reduce((cnt, ares) => {
						if (align[ares]) {
							const val = Common.abbrNumber(align[ares]);
							fr.append(Common.createReportResultElement(ai[1], ares, val))
							++cnt;
						}
						return cnt;
					}, 0)) {
						fr.append(Common.createReportResultElement(ai[1], "pass"));
					}
				}
			});
			return fr;
		}
		return super.value(target);
	}
}

class DispositionColumn extends ITableCell {
	value(target) {
		if (target === "dom") {
			const fr = document.createDocumentFragment();
			const d = this._content;
			[ [ "none", "None", "pass" ], [ "quarantined", "Quar", "fail" ], [ "rejected", "Rej", "fail" ] ].forEach(it => {
				if (d[it[0]]) fr.append(Common.createReportResultElement(it[1], it[2], Common.abbrNumber(d[it[0]])));
			});
			return fr;
		}
		return super.value(target);
	}
}

class ColoredIntColumn extends ITableCell {
	_make_colored_int_element(value, factor) {
		const el = document.createElement("span");
		factor *= value;
		if (factor) el.classList.add("report-result-" + (factor > 0 ? "pass" : "fail"));
		el.append(Common.abbrNumber(value, 1e6));
		return el;
	}
}

class FailsColumn extends ColoredIntColumn {
	value(target) {
		if (target !== "dom") return super.value(target);
		return this._make_colored_int_element(this._content, -1);
	}
}

class ReportListFilterDialog extends ReportFilterDialog {
	constructor(params) {
		params.title = "Report list filter";
		params.item_list = [ "domain", "month", "organization", "dkim", "spf", "disposition", "status" ];
		super(params);
	}

	_fetch_data() {
		this._enable_ui(false);
		this.display_status("wait", "Getting data...");
		window.fetch("list.php?list=filters", {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to fetch the filter list");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._data.loaded_filters = data.filters;
			this._update_ui();
			this._enable_ui(true);
		}).catch(err => {
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this.display_status("wait", null);
		});
	}
}

class ReportListColumnsDialog extends ModalDialog {
	constructor(params) {
		super({ title: "Columns", buttons: [ "apply", "close" ] });
		this._columns = params.columns;
		this._checked = params.checked;
	}

	element() {
		if (!this._element) {
			super.element();
			const fs = document.createElement("fieldset");
			this._content.replaceWith(fs);
			fs.classList.add("vertical-content", "round-border");
			fs.appendChild(document.createElement("legend")).textContent = "Check visible columns";
			this._columns.forEach(ci => {
				fs.append(this._make_column_checkbox(ci.name, ci.content, this._checked.includes(ci.name)));
			});
			fs.addEventListener("change", event => {
				this._buttons[1].disabled = !fs.querySelector("input[type=checkbox]:checked");
			});
			fs.dispatchEvent(new Event("change"));
			this._content = fs;
		}
		return this._element;
	}

	_submit() {
		this._result = [];
		for (const chb of this._content.querySelectorAll("input[type=checkbox]")) {
			if (chb.checked) this._result.push(chb.name);
		}
		this.hide();
	}

	_make_column_checkbox(name, title, checked) {
		const lb = document.createElement("label");
		const cb = lb.appendChild(document.createElement("input"));
		cb.type = "checkbox";
		cb.name = name;
		if (checked) cb.checked = true;
		lb.append(title);
		return lb;
	}
}
