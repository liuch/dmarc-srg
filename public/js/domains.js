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

class DomainList {
	constructor() {
		this._table = null;
		this._scroll = null;
		this._element = document.getElementById("main-block");
		this._sort = { column: "fqdn", direction: "ascent" };
	}

	display() {
		this._make_scroll_container();
		this._make_table();
		this._scroll.appendChild(this._table.element());
		this._element.appendChild(this._scroll);
		this._table.focus();
	}

	update() {
		this._fetch_list();
	}

	title() {
		return "Domain List";
	}

	_fetch_list() {
		this._table.display_status("wait");
		let that = this;

		return window.fetch("domains.php", {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to fetch the domain list");
			return resp.json();
		}).then(function(data) {
			that._table.display_status(null);
			Common.checkResult(data);
			let d = { more: data.more };
			d.rows = data.domains.map(function(it) {
				return that._make_row_data(it);
			});
			if (User.level === "admin" || User.level === "manager") {
				d.rows.push(new NewDomainRow(4));
			}
			let fr = new DomainFrame(d, that._table.last_row_index() + 1);
			that._table.clear();
			that._table.add_frame(fr);
			if (that._sort.column) {
				that._table.sort(that._sort.column, that._sort.direction);
			}
			that._table.focus();
		}).catch(function(err) {
			Common.displayError(err);
			that._table.display_status("error");
		});
	}

	_make_scroll_container() {
		this._scroll = document.createElement("div");
		this._scroll.setAttribute("class", "main-table-container");
	}

	_make_table() {
		this._table = new ITable({
			class:   "main-table domains",
			onclick: function(row) {
				let data = row.userdata();
				if (data) {
					this._display_edit_dialog(data);
				}
			}.bind(this),
			onsort: function(col) {
				let dir = col.sorted() && "toggle" || "ascent";
				this._table.set_sorted(col.name(), dir);
				this._table.sort(col.name(), col.sorted());
				this._sort.column = col.name();
				this._sort.direction = col.sorted();
				this._table.focus();
			}.bind(this),
			onfocus: function(el) {
				scroll_to_element(el, this._scroll);
			}.bind(this)
		});
		[
			{ content: "", sortable: true, name: "status", class: "cell-status" },
			{ content: "FQDN", sortable: true, name: "fqdn" },
			{ content: "Updated", sortable: true, name: "date" },
			{ content: "Description", class: "descr" }
		].forEach(function(col) {
			let c = this._table.add_column(col);
			if (c.name() === this._sort.column) {
				c.sort(this._sort.direction);
			}
		}, this);
	}

	_make_row_data(d) {
		let rd = { cells: [], userdata: d.fqdn };
		rd.cells.push(new DomainStatusCell(d.active));
		rd.cells.push({ content: d.fqdn, class: "fqdn" });
		rd.cells.push(new DomainTimeCell(new Date(d.updated_time)));
		rd.cells.push({ content: d.description || "", class: "descr" });
		return rd;
	}

	_display_edit_dialog(fqdn) {
		let dlg_par = {};
		if (fqdn === "*new") {
			dlg_par["new"]  = true;
			dlg_par.disable = User.level !== "admin";
		}
		else {
			dlg_par.fqdn    = fqdn;
			dlg_par.disable = User.level === "user";
		}
		dlg_par.level = User.level;

		let dlg = new DomainEditDialog(dlg_par);
		this._element.appendChild(dlg.element());
		let that = this;
		dlg.show().then(function(d) {
			if (d) {
				that.update();
			}
		}).finally(function() {
			dlg.element().remove();
			that._table.focus();
		});
	}
}

class DomainStatusCell extends ITableCell {
	constructor(is_active, props) {
		props = props || {};
		let ca = (props.class || "").split(" ");
		ca.push(is_active && "state-green" || "state-gray");
		props.class = ca.filter(function(s) { return s.length > 0; }).join(" ");
		super(is_active, props);
	}

	value(target) {
		if (target === "dom") {
			let div = document.createElement("div");
			div.setAttribute("class", "state-background status-indicator");
			if (!this._title) {
				div.setAttribute("title", this._content && "active" || "inactive");
			}
			return div;
		}
		return this._content;
	}
}

class DomainTimeCell extends ITableCell {
	value(target) {
		if (target === "dom") {
			return this._content && this._content.toUIString() || "";
		}
		if (target === "sort") {
			return this._content && this._content.valueOf() || "";
		}
		super.value(target);
	}
}

class NewDomainRow extends ITableRow {
	constructor(col_cnt) {
		super({
			userdata: "*new",
			cells:    []
		});
		this._col_cnt = col_cnt;
	}

	element() {
		if (!this._element) {
			super.element();
			this._element.classList.add("colspanned", "virtual-item");
			for (let i = 0; i < this._col_cnt; ++i) {
				let cell = document.createElement("div");
				cell.setAttribute("class", "table-cell");
				cell.appendChild(document.createTextNode(!i && "New domain" || "\u00A0"));
				this._element.appendChild(cell);
			}
		}
		return this._element;
	}
}

class DomainFrame extends ITableFrame {
	sort(col_idx, direction) {
		this._sort_dir = (direction === "ascent" && 1) || (direction === "descent" && 2) || 0;
		super.sort(col_idx, direction);
	}

	_compare_cells(c1, c2) {
		if (!c1) {
			return this._sort_dir === 2;
		}
		if (!c2) {
			return this._sort_dir === 1;
		}
		return super._compare_cells(c1, c2);
	}
}

class DomainEditDialog extends VerticalDialog {
	constructor(params) {
		let tl = null;
		let ba = [];
		if (params.level !== "user") ba.push("save");
		if (params["new"]) {
			tl = "New domain";
		}
		else {
			tl = "Domain settings";
			if (params.level !== "user") ba.push("delete");
		}
		ba.push("close");
		super({ title: tl, buttons: ba });
		this._data    = params || {};
		this._note_el = null;
		this._fqdn_el = null;
		this._actv_el = null;
		this._desc_el = null;
		this._c_tm_el = null;
		this._u_tm_el = null;
		this._fetched = false;
	}

	_gen_content() {
		let dis = this._data.disable;

		this._note_el = this._content.appendChild(document.createElement("div"));
		this._note_el.classList.add("hidden", "warn-block");

		let fq = document.createElement("input");
		fq.setAttribute("type", "text");
		if (!this._data["new"]) {
			fq.setAttribute("value", this._data.fqdn);
			fq.disabled = true;
		}
		fq.required = true;
		this._insert_input_row("FQDN", fq);
		this._fqdn_el = fq;

		{
			let en = document.createElement("select");
			let op1 = document.createElement("option");
			op1.setAttribute("value", "yes");
			op1.appendChild(document.createTextNode("Yes"));
			en.appendChild(op1);
			let op2 = document.createElement("option");
			op2.setAttribute("value", "no");
			op2.appendChild(document.createTextNode("No"));
			en.appendChild(op2);
			en.required = true;
			en.disabled = dis;
			this._insert_input_row("Active", en);
			this._actv_el = en;
		}

		let tx = document.createElement("textarea");
		tx.classList.add("description")
		tx.disabled = dis;
		this._insert_input_row("Description", tx);
		this._desc_el = tx;

		let ct = document.createElement("input");
		ct.setAttribute("type", "text");
		ct.disabled = true;
		ct.setAttribute("value","n/a");
		this._insert_input_row("Created", ct);
		this._c_tm_el = ct;

		let ut = document.createElement("input");
		ut.setAttribute("type", "text");
		ut.setAttribute("value","n/a");
		ut.disabled = true;
		this._insert_input_row("Updated", ut);
		this._u_tm_el = ut;

		this._inputs.addEventListener("input", function(event) {
			if (this._fetched || this._data["new"]) {
				this._buttons[1].disabled = (
					this._fqdn_el.value.trim() === "" || (
					this._actv_el.dataset.server === this._actv_el.value &&
					this._desc_el.defaultValue === this._desc_el.value &&
					this._fqdn_el.defaultValue === this._fqdn_el.value)
				);
			}
		}.bind(this));

		if (!this._fetched) this._fetch_data();
	}

	_fetch_data() {
		this._enable_ui(false);
		this._content.appendChild(set_wait_status());
		let uparams = new URLSearchParams();
		uparams.set("domain", this._data.fqdn || "");

		let that = this;
		window.fetch("domains.php?" + uparams.toString(), {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to fetch the domain data");
			return resp.json();
		}).then(function(data) {
			that._fetched = true;
			Common.checkResult(data);
			if (data.created_time) data.created_time = new Date(data.created_time);
			if (data.updated_time) data.updated_time = new Date(data.updated_time);
			that._update_ui(data);
			that._enable_ui(true);
		}).catch(function(err) {
			Common.displayError(err);
			that._content.appendChild(set_error_status(null, err.message));
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
		});
	}

	_update_ui(data) {
		if (data.verification === "dns") {
			this._note_el.classList.remove("hidden");
			let lm = document.createElement("a");
			lm.href = "";
			lm.textContent = "Learn more";
			this._note_el.append("Domain verification is required! ", lm);
			lm.addEventListener("click", event => {
				event.preventDefault();
				this._display_verification_dialog(data.verification_data, lm);
			});
		}
		if (this._data["new"]) return;

		let val = "";
		for (let i = 0; i < this._actv_el.options.length; ++i) {
			let op = this._actv_el.options[i];
			if (data.active === (op.value === "yes")) {
				op.setAttribute("selected", "selected");
				val = op.value;
			}
			else {
				op.removeAttribute("selected");
			}
		}
		this._actv_el.value = val;
		this._actv_el.dataset.server = val;
		this._desc_el.appendChild(document.createTextNode(data.description || ""));
		this._c_tm_el.setAttribute("value", data.created_time && data.created_time.toUIString() || "n/a");
		this._u_tm_el.setAttribute("value", data.updated_time && data.updated_time.toUIString() || "n/a");
	}

	_add_button(container, text, type) {
		let btn = null;
		if (type === "save") {
			text = "Save";
			btn = document.createElement("button");
			btn.disabled = true;
			btn.addEventListener("click", this._save.bind(this));
		}
		else if (type === "delete") {
			text = "Delete";
			btn = document.createElement("button");
			btn.addEventListener("click", this._confirm_delete.bind(this));
		}
		else {
			super._add_button(container, text, type);
			return;
		}
		btn.setAttribute("type", "button");
		btn.appendChild(document.createTextNode(text));
		container.appendChild(btn);
		this._buttons.push(btn);
	}

	_enable_ui(en) {
		let dis = this._data.disable;
		this._fqdn_el.disabled = !en || !this._data["new"];
		this._actv_el.disabled = !en || dis;
		this._desc_el.disabled = !en || dis;
		for (let i = 2; i < this._buttons.length - 1; ++i) {
			this._buttons[i].disabled = !en;
		}

		this._update_first_last();
		if (this._first) {
			this._first.focus();
		}
	}

	_save() {
		this._enable_ui(false);
		let em = this._content.querySelector(".error-message");
		em && em.remove();
		this._content.appendChild(set_wait_status(null, "Sending data to the server..."));

		let body = {};
		body.fqdn        = this._data["new"] && this._fqdn_el.value || this._data.fqdn;
		body.action      = this._data["new"] && "add" || "update";
		body.active      = this._actv_el.value === "yes";
		body.description = this._desc_el.value;

		let that = this;
		window.fetch("domains.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to " + (body.new && "add" || "update") + " the domain data");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			that._result = body;
			that.hide();
			Notification.add({
				text: "The domain " + body.fqdn + " was " + (body.action === "add" && "added" || "updated")
			});
		}).catch(function(err) {
			Common.displayError(err);
			that._content.appendChild(set_error_status(null, err.message));
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
			that._enable_ui(true);
		});
	}

	_confirm_delete() {
		if (confirm("Are sure you want to delete this domain?")) {
			this._delete();
		}
	}

	_delete() {
		this._enable_ui(false);
		let em = this._content.querySelector(".error-message");
		em && em.remove();
		this._content.appendChild(set_wait_status(null, "Sending a request to the server..."))

		let body = {};
		body.fqdn   = this._data.fqdn;
		body.action = "delete";

		let that = this;
		window.fetch("domains.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to delete the domain");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			that._result = data;
			that.hide();
			Notification.add({ text: "The domain " + body.fqdn + " was removed" });
		}).catch(function(err) {
			Common.displayError(err);
			that._content.appendChild(set_error_status(null, err.message));
		}).finally(function() {
			that._content.querySelector(".wait-message").remove();
			that._enable_ui(true);
		});
	}

	_display_verification_dialog(data, source_el) {
		const dlg = new VerificationDialog(data);
		this._element.classList.add("hidden");
		document.getElementById("main-block").append(dlg.element());
		dlg.show().finally(() => {
			dlg.element().remove();
			this._element.classList.remove("hidden");
			source_el.focus();
		});
	}
}

class VerificationDialog extends ModalDialog {
	constructor(data) {
		super({ title: "Domain verification information", buttons: [ "close" ] });
		this._data = data;
	}

	_gen_content() {
		this._element.children[0].classList.add("verification");
		this._content.classList.add("vertical-content");
		const t1 = document.createElement("div");
		t1.textContent = "To ensure that you are the owner of the domain, add a TXT record to the DNS settings at your domain registrar with the following content:";
		const t2 = document.createElement("textarea");
		t2.textContent = this._data;
		t2.readOnly = true;
		const t3 = document.createElement("div");
		t3.textContent = "Once the domain has been successfully added, the TXT record can be deleted.";
		const t4 = document.createElement("div");
		t4.textContent = "Important! Some registrars may require additional time to publish your verification code. If the tool can't find your new TXT record, wait an hour before you try again."
		this._content.append(t1, t2, t3, t4);
	}
}
