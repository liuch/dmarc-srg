/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2023-2025 Aleksey Andreev (liuch)
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

const User = { name: null, level: null, auth_type: null };

class UserList {
	constructor() {
		this._page = null;
		this._table = null;
		this._scroll = null;
		this._element = document.getElementById("main-block");
		this._sort = { column: "name", direction: "ascent" };
	}

	display() {
		this._make_page_container();
		this._make_scroll_container();
		this._make_table();
		this._scroll.append(this._table.element());
		this._page.append(this._scroll);
		this._element.appendChild(this._page);
		this._table.focus();
	}

	update() {
		this._fetch_list();
	}

	title() {
		return "User List";
	}

	_fetch_list() {
		this._table.display_status("wait");
		return window.fetch("users.php", {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to fetch the user list");
			return resp.json();
		}).then(data => {
			this._table.display_status(null);
			Common.checkResult(data);
			const d = { more: data.more };
			d.rows = data.users.map(it => this._make_row_data(it));
			d.rows.push(new NewUserRow(5));
			const fr = new UserFrame(d, this._table.last_row_index() + 1);
			this._table.clear();
			this._table.add_frame(fr);
			if (this._sort.column) this._table.sort(this._sort.column, this._sort.direction);
			this._table.focus();
		}).catch(err => {
			Common.displayError(err);
			this._table.display_status("error", err.message || null);
		});
	}

	_make_page_container() {
		this._page = document.createElement("div");
		this._page.classList.add("page-container");
	}

	_make_scroll_container() {
		this._scroll = document.createElement("div");
		this._scroll.classList.add("table-wrapper");
	}

	_make_table() {
		this._table = new ITable({
			class: "main-table users",
			onclick: row => {
				const data = row.userdata();
				if (data) this._display_edit_dialog(data);
			},
			onsort: col => {
				const dir = col.sorted() && "toggle" || "ascent";
				this._table.set_sorted(col.name(), dir);
				this._table.sort(col.name(), col.sorted());
				this._sort.column = col.name();
				this._sort.direction = col.sorted();
				this._table.focus();
			},
			onfocus: el => scroll_to_element(el, this._scroll)
		});
		[
			{ content: "", sortable: true, name: "status", class: "cell-status" },
			{ content: "Name", sortable: true, name: "name" },
			{ content: "Access level", sortable: false, name: "level" },
			{ content: "Domains", sortable: false, name: "domains" },
			{ content: "Updated", sortable: true, name: "date" },
		].forEach(col => {
			const c = this._table.add_column(col);
			if (c.name() === this._sort.column) c.sort(this._sort.direction);
		});
	}

	_make_row_data(d) {
		const rd = { cells: [], userdata: d.name };
		rd.cells.push(new UserStatusCell(d.enabled));
		rd.cells.push({ content: d.name, class: "user-name" });
		rd.cells.push({ content: d.level, class: "user-level" });
		rd.cells.push({ content: d.domains });
		rd.cells.push(new UserTimeCell(new Date(d.updated_time)));
		return rd;
	}

	_display_edit_dialog(username) {
		const dlg_par = {};
		if (username === "*new") {
			dlg_par["new"] = true;
		}
		else {
			dlg_par.name    = username;
			dlg_par.minimal = User.level !== "admin";
		}
		const dlg = new UserEditDialog(dlg_par);
		this._element.appendChild(dlg.element());
		dlg.show().then(d => {
			if (d) this.update();
		}).finally(() => {
			dlg.element().remove();
			this._table.focus();
		});
	}
}

class UserStatusCell extends ITableCell {
	constructor(is_enabled, props) {
		props ||= {};
		const ca = (props.class || "").split(" ");
		ca.push(is_enabled && "state-green" || "state-gray");
		props.class = ca.filter(s => s.length > 0).join(" ");
		super(is_enabled, props);
	}

	value(target) {
		if (target === "dom") {
			const div = document.createElement("div");
			div.classList.add("state-background", "status-indicator");
			if (!this._title) div.title = this._content && "enabled" || "disabled";
			return div;
		}
		return this._content;
	}
}

class UserTimeCell extends ITableCell {
	value(target) {
		if (target === "dom") return this._content && this._content.toUIString() || "";
		if (target === "sort") return this._content && this._content.valueOf() || 0;
		super.value(target);
	}
}

class NewUserRow extends ITableRow {
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
				const cell = document.createElement("div");
				cell.classList.add("table-cell");
				cell.textContent = !i && "New user" || "\u00A0";
				this._element.appendChild(cell);
			}
		}
		return this._element;
	}
}

class UserFrame extends ITableFrame {
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

class UserEditDialog extends VerticalDialog {
	constructor(params) {
		params ||= {}
		const ba = [];
		if (!params.minimal) ba.push("save");
		ba.push("close");
		let title = null;
		if (!params["new"]) {
			title = "User settings";
			if (!params.minimal) ba.splice(1, 0, "delete");
		} else {
			title = "New user";
		}
		super({ title: title, buttons: ba });
		this._data    = params;
		this._name_el = null;
		this._alvl_el = null;
		this._actv_el = null;
		this._doms_el = null;
		this._pasw_el = null;
		this._c_tm_el = null;
		this._u_tm_el = null;
		this._domains = new Set();
		this._fetched = false;
	}

	_gen_content() {
		const min = this._data.minimal;

		// Name
		const nm = document.createElement("input");
		nm.type = "text";
		if (!this._data["new"]) {
			nm.value = this._data.name;
			nm.disabled = true;
		}
		nm.required = true;
		nm.maxLength = 32;
		this._insert_input_row("Name", nm);
		this._name_el = nm;

		// Level
		const lv = document.createElement("select");
		[ "Manager", "User" ].forEach(val => {
			const op = document.createElement("option");
			op.value = val.toLowerCase();
			op.textContent = val;
			lv.appendChild(op);
		});
		lv.required = true;
		lv.value = "user";
		if (min) lv.disabled = true;
		this._insert_input_row("Level", lv);
		this._alvl_el = lv;

		if (!min) {
			// Enabled
			const en = document.createElement("select");
			[ "Yes", "No" ].forEach(val => {
				const op = document.createElement("option");
				op.value = val.toLowerCase();
				op.textContent = val;
				en.appendChild(op);
			});
			en.required = true;
			this._insert_input_row("Enabled", en);
			this._actv_el = en;

			// Domains
			const dm = document.createElement("multi-select");
			dm.setAttribute("placeholder", "No domains");
			dm.setLabel("Domains");
			this._insert_input_row("Domains", dm);
			this._doms_el = dm;
		}

		// Password
		const sp = document.createElement("span");
		sp.classList.add("value");
		sp.textContent = "None ";
		if (!this._data["new"]) {
			const pw = document.createElement("a");
			pw.href = "";
			pw.textContent = "[ Change ]";
			sp.appendChild(pw);
			pw.addEventListener("click", event => {
				event.preventDefault();
				this._display_password_dialog(pw);
			});
		}
		this._insert_input_row("Password", sp);
		this._pasw_el = sp;

		// Created
		const ct = document.createElement("input");
		ct.type = "text";
		ct.value = "n/a";
		ct.disabled = true;
		this._insert_input_row("Created", ct);
		this._c_tm_el = ct;

		// Updated
		const ut = document.createElement("input");
		ut.type = "text";
		ut.value = "n/a";
		ut.disabled = true;
		this._insert_input_row("Updated", ut);
		this._u_tm_el = ut;


		this._inputs.addEventListener("input", event => this._input_handler(event));
		this._doms_el && this._doms_el.addEventListener("change", event => this._input_handler(event));

		if (!this._fetched) this._fetch_data();
	}

	_input_handler(event) {
		if (!this._fetched) return;

		let dis = true;
		if (this._name_el.value.trim() !== "" && this._alvl_el.value !== "") {
			if (this._alvl_el.dataset.server !== this._alvl_el.value) {
				dis = false;
			} else if (this._actv_el && this._actv_el.dataset.server !== this._actv_el.value) {
				dis = false;
			} else if (this._doms_el) {
				const dlist = this._doms_el.getValues();
				if (dlist.length != this._domains.size || !dlist.every(d => this._domains.has(d))) {
					dis = false;

				}
			}
		}
		this._buttons[1].disabled = dis;
	}

	_add_button(container, text, type) {
		let dsbl = false;
		let lstn = null;
		switch (type) {
			case "save":
				text = "Save";
				dsbl = true;
				lstn = this._save.bind(this);
				break;
			case "delete":
				text = "Delete";
				lstn = this._confirm_delete.bind(this);
				break;
			default:
				super._add_button(container, text, type);
				return;
		}
		const btn = document.createElement("button");
		btn.type = "button";
		btn.disabled = dsbl;
		btn.textContent = text;
		btn.addEventListener("click", lstn);
		container.appendChild(btn);
		this._buttons.push(btn);
	}

	_fetch_data() {
		this._enable_ui(false);
		this.display_status("wait", "Getting data...");
		const url = new URL("users.php", document.location);
		url.searchParams.set("user", this._data.name || "");

		return window.fetch(url, {
			method: "GET",
			cache: "no-store",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to fetch data");
			return resp.json();
		}).then(data => {
			this._fetched = true;
			Common.checkResult(data);
			[ "created_time", "updated_time" ].forEach(it => {
				data[it] && (data[it] = new Date(data[it]))
			});
			this._update_ui(data);
			this._enable_ui(true);
		}).catch(err => {
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this.display_status("wait", null);
		});
	}

	_enable_ui(en) {
		this._name_el.disabled = !en || !this._data["new"];
		this._alvl_el.disabled = !en || this._data.minimal;
		if (this._actv_el) this._actv_el.disabled = !en;
		if (this._doms_el) this._doms_el.disabled = !en;
		for (let i = 2; i < this._buttons.length - 1; ++i) {
			this._buttons[i].disabled = !en;
		}
		this.focus();
	}

	_update_ui(data) {
		if (data.level) this._set_option_value(this._alvl_el, data.level);
		if (this._actv_el) this._set_option_value(this._actv_el, (data.enabled || this._data["new"]) ? "yes" : "no");
		if (this._doms_el) {
			this._domains = new Set(data.domains.assigned);
			data.domains.available.forEach(d => this._doms_el.appendItem(d));
			if (data.domains.assigned) this._doms_el.setValues(data.domains.assigned);
		}
		if (data.password) this._set_password_yes();
		this._c_tm_el.value = data.created_time && data.created_time.toUIString() || "n/a";
		this._u_tm_el.value = data.updated_time && data.updated_time.toUIString() || "n/a";
	}

	_set_password_yes() {
		this._pasw_el.childNodes[0].textContent = "Yes ";
	}

	_set_option_value(el, data) {
		let val = "";
		for (let i = 0; i < el.options.length; ++i) {
			const op = el.options[i];
			if (data === op.value) {
				op.setAttribute("selected", "");
				val = op.value;
			}
			else {
				op.removeAttribute("selected");
			}
		}
		el.value = val;
		el.dataset.server = val;
	}

	_save() {
		this._enable_ui(false);
		this.display_status("wait", "Sending data to the server...");

		const body = {};
		body.name    = this._data["new"] && this._name_el.value || this._data.name;
		body.level   = this._alvl_el.value;
		body.enabled = this._actv_el && this._actv_el.value === "yes";
		body.action  = this._data["new"] && "add" || "update";
		if (this._doms_el) {
			const dlist = this._doms_el.getValues();
			if (this._domains.size != dlist.length || dlist.some(d => !this._domains.has(d))) {
				body.domains = dlist;
			}
		}

		window.fetch("users.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(resp => {
			if (!resp.ok)
				throw new Error("Failed to " + (body.new && "add" || "update") + " the user data");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._result = body;
			this.hide();
			Notification.add({
				text: `The user ${body.name} successfully ` + (body.action === "add" && "added" || "updated")
			});
		}).catch(err => {
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this.display_status("wait", null);
			this._enable_ui(true);
		});
	}

	_confirm_delete() {
		if (confirm("Are sure you want to delete this user?")) this._delete();
	}

	_delete() {
		this._enable_ui(false);
		this.display_status("wait", "Sending a request to the server...");

		const body = { name: this._data.name, action: "delete" };
		window.fetch("users.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to delete the user");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._result = data;
			this.hide();
			Notification.add({ text: `The user ${body.name} successfully removed` });
		}).catch(err => {
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this.display_status("wait", null);
			this._enable_ui(true);
		});
	}

	_display_password_dialog(source_el) {
		const dlg = new PasswordDialog({
			username:         this._data.name,
			current_password: this._data.name === User.name
		});
		this._element.classList.add("hidden");
		document.getElementById("main-block").appendChild(dlg.element());
		dlg.show().then(d => {
			if (d) this._set_password_yes();
		}).finally(() => {
			dlg.element().remove();
			this._element.classList.remove("hidden");
			source_el.focus();
		});
	}
}

class PasswordDialog extends VerticalDialog {
	constructor(params) {
		super({ title: `Password change [${params.username}]`, buttons: [ "apply", "cancel" ] });
		this._data   = params;
		this._apl_bt = null;
		this._pw_cur = null;
		this._pw_nw1 = null;
		this._pw_nw2 = null;
	}

	_gen_content() {
		if (this._data.current_password) {
			const pw_cur = document.createElement("input");
			pw_cur.type = "password";
			pw_cur.required = true;
			this._insert_input_row("Current password", pw_cur);
			this._pw_cur = pw_cur;
		}

		const pw_nw1 = document.createElement("input");
		pw_nw1.type = "password";
		pw_nw1.required = true;
		this._insert_input_row("New password", pw_nw1);
		this._pw_nw1 = pw_nw1;

		const pw_nw2 = document.createElement("input");
		pw_nw2.type = "password";
		pw_nw2.required = true;
		this._insert_input_row("Confirm password", pw_nw2);
		this._pw_nw2 = pw_nw2;

		this._inputs.addEventListener("input", event => {
			this._apl_bt.disabled = [ this._pw_cur, this._pw_nw1, this._pw_nw2 ].some(el => {
				return el && el.value === "";
			});
		});
	}

	_add_button(c, t, type) {
		super._add_button(c, t, type);
		if (type === "submit") {
			this._apl_bt = this._buttons[this._buttons.length - 1];
			this._apl_bt.disabled = true;
		}
	}

	_submit() {
		if (this._pw_nw1.value !== this._pw_nw2.value) {
			this.display_status("error", "New password and confirm password don't match");
			this._pw_nw1.focus();
			return;
		}

		this.display_status("wait", "Updating the password...");
		this._enable_ui(false);

		const body = {
			name: this._data.username,
			action: "set_password",
			new_password: this._pw_nw1.value
		};
		if (this._pw_cur) body.password = this._pw_cur.value;
		window.fetch("users.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(resp => {
			if (!resp.ok)
				throw new Error("Failed to " + (body.new && "add" || "update") + " the user data");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._result = true;
			this.hide();
			Notification.add({ text: "The password has been successfully updated" });
		}).catch(err => {
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this.display_status("wait", null);
			this._enable_ui(true);
		});
	}

	_enable_ui(en) {
		if (this._pw_cur) this._pw_cur.disabled = !en;
		this._pw_nw1.disabled = !en;
		this._pw_nw2.disabled = !en;
		this.focus();
	}
}
