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

class ITable {
	constructor(params) {
		this._table = null;
		this._class = null;
		this._header = null;
		this._status = null;
		this._frames = [];
		this._columns = [];
		this._body = null;
		this._onsort = null;
		this._onclick = null;
		this._onfocus = null;
		if (params) {
			this._class = params.class || null;
			this._onsort = params.onsort || null;
			this._onclick = params.onclick || null;
			this._onfocus = params.onfocus || null;
		}
		this._focused = false;
		this._focused_row = null;
		this._selected_rows = [];
	}

	element() {
		if (!this._table) {
			let that = this;
			this._table = document.createElement("table");
			if (this._class)
				this._table.setAttribute("class", this._class);
			this._table.setAttribute("tabindex", -1);
			this._table.addEventListener("focus", function(event) {
				that._focused = true;
				that._update_focus();
			}, true);
			this._table.addEventListener("blur", function(event) {
				that._focused = false;
				that._update_focus();
			}, true);
			this._header = document.createElement("tr");
			this._header.addEventListener("click", function(event) {
				let col = that.get_column_by_element(event.target);
				if (col && col.is_sortable()) {
					if (that._onsort) {
						that._onsort(col);
					}
				}
			});
			this._table.appendChild(this._header);
			this._fill_columns();
			this._body = document.createElement("tbody");
			this._body.addEventListener("click", function(event) {
				let row = that._get_row_by_element(event.target);
				if (row) {
					that._set_selected_rows([ row ]);
					if (that._onclick)
						that._onclick(row.userdata(), row.id());
				}
			});
			this._body.addEventListener("focus", function(event) {
				let row = that._get_row_by_element(event.target);
				if (row) {
					that._update_focused_row(row);
					if (that._onfocus)
						that._onfocus(row.element());
				}
			}, true);
			this._body.addEventListener("blur", function(event) {
				let row = that._get_row_by_element(event.target);
				if (row) {
					row.onfocus(false);
				}
			}, true);
			this._body.addEventListener("keydown", function(event) {
				let row = null;
				switch (event.code) {
					case "ArrowDown":
						row = that._get_row(that._focused_row !== null && (that._focused_row.id() + 1) || 0);
						break;
					case "ArrowUp":
						if (that._focused_row) {
							let id = that._focused_row.id();
							if (id >= 0)
								row = that._get_row(id - 1);
						}
						else {
							row = that._get_row(0);
						}
						break;
					case "PageUp":
						if (that._focused_row && that._frames.length > 0) {
							let c_id = that._focused_row.id();
							let f_id = that._frames[0].first_index();
							if (c_id == f_id)
								break;
							let s_el = that._get_scroll_element();
							if (s_el) {
								let r_ht = that._focused_row.element().getBoundingClientRect().height;
								let s_ht = s_el.getBoundingClientRect().height;
								let n_id = Math.max(c_id - Math.floor(s_ht / r_ht) - 1, f_id);
								row = that._get_row(n_id);
							}
						}
						break;
					case "PageDown":
						if (that._focused_row && that._frames.length > 0) {
							let c_id = that._focused_row.id();
							let l_id = that._frames[that._frames.length - 1].last_index();
							if (c_id == l_id)
								break;
							let s_el = that._get_scroll_element();
							if (s_el) {
								let r_ht = that._focused_row.element().getBoundingClientRect().height;
								let s_ht = s_el.getBoundingClientRect().height;
								let n_id = Math.min(c_id + Math.floor(s_ht / r_ht) - 1, l_id);
								row = that._get_row(n_id);
							}
						}
						break;
					case "Home":
						if (that._frames.length > 0) {
							let first_frame = that._frames[0];
							row = first_frame.row(first_frame.first_index());
						}
						break;
					case "End":
						if (that._frames.length > 0) {
							let last_frame = that._frames[that._frames.length - 1];
							row = last_frame.row(last_frame.last_index());
						}
						break;
					case "Enter":
					case "NumpadEnter":
						if (that._onclick && that._focused_row)
							that._onclick(that._focused_row.userdata(), that._focused_row.id());
						event.preventDefault();
						return;
				}
				if (row) {
					row.element().focus();
					that._set_selected_rows([ row ]);
					event.preventDefault();
				}
			});
			this._table.appendChild(this._body);
		}
		return this._table;
	}

	more() {
		return this._frames.length > 0 && this._frames[this._frames.length - 1].more();
	}

	frames_count() {
		return this._frames.length;
	}

	add_column(data) {
		let col = new ITableColumn(data.content, {
			name:     data.name,
			sortable: data.sortable,
			sorted:   data.sorted
		});
		this._columns.push(col);
		if (this._header)
			this._header.appendChild(col.element());
		return col;
	}

	get_column_by_element(el) {
		el = el && el.closest("th");
		if (el) {
			for (let i = 0; i < this._columns.length; ++i) {
				let col = this._columns[i];
				if (el === col.element())
					return col;
			}
		}
	}

	display_status(status, text) {
		if (this._status) {
			if (!status) {
				this._status.remove();
				this._status = null;
				return;
			}
		}
		else {
			this.element();
			this._status = document.createElement("tr");
			let col = document.createElement("td");
			col.setAttribute("colspan", this._columns.length || 1);
			this._status.appendChild(col);
		}

		let el = this._status.firstChild;
		if (status === "wait")
			set_wait_status(el);
		else {
			remove_all_children(this._body);
			if (status === "nodata")
				el.appendChild(document.createTextNode(text || "No data"));
			else
				set_error_status(el, text);
		}
		this._body.appendChild(this._status);
	}

	last_row_index() {
		let idx = -1;
		if (this._frames.length > 0) {
			idx = this._frames[this._frames.length - 1].last_index();
		}
		return idx;
	}

	add_frame(frame) {
		if (frame.count() === 0) {
			if (this._frames.length === 0)
				this.display_status("nodata");
			return
		}

		if (this._frames.length > 0 && this._frames[0].first_index() > frame.last_index()) {
			this._frames.unshift(frame);
			if (this._body)
				this._body.insertBefore(frame.element(), this._body.firstChild);
		}
		else {
			this._frames.push(frame);
			if (this._body)
				this._body.appendChild(frame.element());
		}
	}

	clear() {
		this._frames = [];
		remove_all_children(this._body);
		this._focused_row = null;
		this._selected_rows = [];
	}

	focus() {
		if (!this._focused_row) {
			if (this._frames.length > 0) {
				let fr = this._frames[0];
				this._focused_row = fr.row(fr.first_index());
			}
		}
		if (this._focused_row)
			this._focused_row.element().focus();
	}

	sort(col_name, direction) {
		if (this._frames.length == 1) {
			for (let i = 0; i < this._columns.length; ++i) {
				let col = this._columns[i];
				if (col.is_sortable() && col.name() === col_name) {
					let fr = this._frames[0];
					fr.sort(i, direction);
					if (this._body) {
						remove_all_children(this._body);
						this._body.appendChild(fr.element());
					}
					return;
				}
			}
		}
	}

	set_sorted(col_name, direction) {
		this._columns.forEach(function(col) {
			if (col.is_sortable()) {
				if (col.name() !== col_name) {
					col.sort(null);
				}
				else {
					if (direction === "toggle") {
						direction = null;
						if (col.sorted() === "ascent") {
							direction = "descent";
						}
						else if (col.sorted() === "descent") {
							direction = "ascent";
						}
					}
					col.sort(direction);
				}
			}
		});
	}

	_fill_columns() {
		this._columns.forEach(function(col) {
			this._header.appendChild(col.element());
		}, this);
	}

	_get_row(row_id) {
		for (let i = 0; i < this._frames.length; ++i) {
			let fr = this._frames[i];
			if (fr.last_index() >= row_id) {
				if (fr.first_index() <= row_id)
					return fr.row(row_id);
			}
		}
		return null;
	}

	_get_row_by_element(el) {
		let row = null;
		if (el) {
			el = el.closest("tr");
			if (el) {
				let id = parseInt(el.getAttribute("data-id"));
				if (id !== NaN)
					row = this._get_row(id);
			}
		}
		return row;
	}

	_update_focus() {
		if (this._focused)
			this._table.classList.add("focused");
		else
			this._table.classList.remove("focused");
	}

	_update_focused_row(row) {
		if (this._focused_row && row !== this._focused_row) {
			this._focused_row.tabindex(-1);
		}
		this._focused_row = row;
		this._focused_row.tabindex(0);
		this._focused_row.onfocus(true);
	}

	_set_selected_rows(rows) {
		this._selected_rows.forEach(function(row) {
			row.select(false);
		});
		rows.forEach(function(row) {
			row.select(true);
		});
		this._selected_rows = rows;
	}

	_get_scroll_element() {
		let t_rect = this._table.getBoundingClientRect();
		let p_elem = this._table.parentElement;
		while (p_elem) {
			let p_rect = p_elem.getBoundingClientRect();
			if (t_rect.top < p_rect.top || t_rect.bottom > p_rect.bottom)
				return p_elem;
		}
	}
}

class ITableFrame {
	constructor(data, pos) {
		this._pos = pos;
		this._rows = [];
		this._more = data.more && true || false;
		let id = pos;
		data.rows.forEach(function(row) {
			this._add_row(row, id++);
		}, this);
	}

	count() {
		return this._rows.length;
	}

	first_index() {
		return this._pos;
	}

	last_index() {
		let cnt = this._rows.length;
		if (cnt > 0) {
			return this._pos + cnt - 1;
		}
		return null;
	}

	row(id) {
		let idx = id - this._pos;
		if (idx >= 0 && idx < this._rows.length) {
			return this._rows[idx];
		}
		return null;
	}

	more() {
		return this._more;
	}

	element() {
		let fr = document.createDocumentFragment();
		this._rows.forEach(function(row) {
			fr.appendChild(row.element());
		});
		return fr;
	}

	sort(col_idx, direction) {
		let dir = (direction === "ascent" && 1) || (direction === "descent" && 2) || 0;
		if (dir) {
			this._rows.sort(function(a, b) {
				let a_val = a.cell(col_idx).value();
				let b_val = b.cell(col_idx).value();
				if (dir === 1) {
					return a_val > b_val;
				}
				return a_val < b_val;
			});
			let id = this._pos;
			this._rows.forEach(function(row) {
				row.id(id++);
			});
		}
	}

	_add_row(row, id) {
		this._rows.push(new ITableRow(row, id));
	}
}

class ITableRow {
	constructor(data, id) {
		this._id = id;
		this._focused = false;
		this._tabindex = -1;
		this._selected = false;
		this._element = null;
		this._userdata = data.userdata || null;
		this._cells = [];
		data.cells.forEach(function(col) {
			let props = null;
			if (col.title || col.class)
				props = { title: col.title || null, class: col.class || null };
			this._cells.push(new ITableCell(col.content, props));
		}, this);
	}

	userdata() {
		return this._userdata;
	}

	element() {
		if (!this._element) {
			this._element = document.createElement("tr");
			this._element.setAttribute("data-id", this._id);
			this._cells.forEach(function(col) {
				this._element.appendChild(col.element());
			}, this);
			this._update_focus();
			this._update_tabindex();
			this._update_select();
		}
		return this._element;
	}

	onfocus(flag) {
		this._focused = flag;
		if (this._element)
			this._update_focus();
	}

	tabindex(index) {
		if (this._tabindex !== index) {
			this._tabindex = index;
			this._update_tabindex();
		}
	}

	select(flag) {
		this._selected = flag;
		if (this._element)
			this._update_select();
	}

	id(new_id) {
		if (new_id !== undefined && new_id !== this._id) {
			this._id = new_id;
			if (this._element) {
				this._element.setAttribute("data-id", this._id);
			}
		}
		return this._id;
	}

	cell(index) {
		return this._cells[index] || null;
	}

	_update_focus() {
		if (this._focused)
			this._element.classList.add("focused");
		else
			this._element.classList.remove("focused");
	}

	_update_tabindex() {
		this._element.setAttribute("tabindex", this._tabindex);
	}

	_update_select() {
		if (this._selected) {
			this._element.classList.add("selected");
		}
		else {
			this._element.classList.remove("selected");
		}
	}
}

class ITableCell {
	_element_name = "td";

	constructor(content, props) {
		this._element = null;
		this._content = content;
		if (props) {
			this._title = props.title || null;
			this._class = props.class || null;
		}
	}

	element() {
		if (!this._element) {
			this._element = document.createElement(this._element_name);
			if (this._title)
				this._element.setAttribute("title", this._title);
			if (this._class)
				this._element.setAttribute("class", this._class);
			if (this._content) {
				if (typeof(this._content) === "object")
					this._element.appendChild(this._content)
				else
					this._element.appendChild(document.createTextNode(this._content));
			}
		}
		return this._element;
	}

	value() {
		return typeof(this._content) !== "object" && this._content || null;
	}
}

class ITableColumn extends ITableCell {
	_element_name = "th";

	constructor(content, props) {
		super(content, props);
		this._name = props.name;
		this._sortable = !!props.sortable;
		this._sorted = props.sorted || null;
	}

	element() {
		if (this._element !== super.element()) {
			this._update_sorted();
		}
		return this._element;
	}

	is_sortable() {
		return this._sortable;
	}

	sort(dir) {
		if (this._sorted !== dir) {
			this._sorted = dir || null;
			if (this._element) {
				this._update_sorted();
			}
		}
	}

	sorted() {
		return this._sorted;
	}

	name() {
		return this._name;
	}

	_update_sorted() {
		if (this._sortable) {
			this._element.classList.add("sortable");
			let c_act = {
				asc: "remove",
				des: "remove"
			};
			if (this._sorted) {
				this._element.classList.add("arrows");
				if (this._sorted === "ascent") {
					c_act["asc"] = "add";
				}
				else if (this._sorted === "descent") {
					c_act["des"] = "add";
				}
			}
			else {
				this._element.classList.remove("arrows");
			}
			for (let key in c_act) {
				this._element.classList[c_act[key]]("sorted-" + key);
			}
		}
	}
}

class ModalDialog {
	constructor(params) {
		this._params   = params;
		this._element  = null;
		this._title    = null;
		this._buttons  = [];
		this._content  = null;
		this._first    = null;
		this._last     = null;
		this._result   = null;
		this._callback = null;
	}

	element() {
		if (!this._element) {
			let ovl = document.createElement("div");
			ovl.setAttribute("class", "dialog-overlay hidden");
			let dlg = document.createElement("div");
			dlg.setAttribute("class", "dialog");
			let con = document.createElement("div");
			con.setAttribute("class", "container");
			this._title = document.createElement("div");
			this._title.setAttribute("class", "title");
			let cbt = document.createElement("button");
			cbt.setAttribute("type", "button");
			cbt.setAttribute("class", "close-btn");
			cbt.appendChild(document.createTextNode("x"));
			this._title.appendChild(cbt);
			con.appendChild(this._title);
			let frm = document.createElement("form");
			this._content = document.createElement("div");
			frm.appendChild(this._content);
			let bdv = document.createElement("div");
			bdv.setAttribute("class", "dialog-buttons");
			this._buttons = [ cbt ];
			this._add_buttons(bdv);
			frm.appendChild(bdv);
			con.appendChild(frm);
			dlg.appendChild(con);
			ovl.appendChild(dlg);
			this._element = ovl;
			this._gen_content();
			this._update_first_last();
			let that = this;
			cbt.addEventListener("click", function(event) {
				that.hide();
			});
			this._element.addEventListener("click", function(event) {
				if (event.target === this && that._params.overlay_click !== "ignore") {
					that.hide();
				}
			});
			frm.addEventListener("keydown", function(event) {
				if (event.key == "Tab") {
					if (!event.shiftKey) {
						if (event.target == that._last) {
							that._first.focus();
							event.preventDefault();
						}
					}
					else {
						if (event.target == that._first) {
							that._last.focus();
							event.preventDefault();
						}
					}
				}
			});
			frm.addEventListener("submit", function(event) {
				event.preventDefault();
				that._submit();
			});
		}
		return this._element;
	}

	show() {
		if (this._element) {
			this._element.classList.remove("hidden");
			if (this._first)
				this._first.focus();
		}
		let that = this;
		return new Promise(function(resolve, reject) {
			that._callback = resolve;
		});
	}

	hide() {
		if (this._element) {
			this._element.classList.add("hidden");
		}
		this._callback && this._callback(this._result);
	}

	_add_buttons(container) {
		let bl = this._params.buttons || [];
		bl.forEach(function(bt) {
			let name = null;
			let type = null;
			if (bt == "ok") {
				name = "Ok";
				type = "submit";
			}
			else if (bt == "apply") {
				name = "Apply";
				type = "submit";
			}
			else if (bt == "reset") {
				name = "Reset";
				type = "reset";
			}
			else if (bt == "login") {
				name = "Log in";
				type = "submit";
			}
			else if (bt == "cancel") {
				name = "Cancel";
				type = "close";
			}
			this._add_button(container, name, type);
		}, this);
	}

	_add_button(container, text, type) {
		let btn = document.createElement("button");
		if (type == "close") {
			btn.setAttribute("type", "button");
			btn.addEventListener("click", this.hide.bind(this));
		}
		else {
			btn.setAttribute("type", type);
		}
		btn.appendChild(document.createTextNode(text));
		container.appendChild(btn);
		this._buttons.push(btn);
	}

	_gen_content() {
	}

	_update_first_last() {
		this._first = null;
		this._last  = null;
		let list = this._element.querySelector("form").elements;
		for (let i = 0; i < list.length; ++i) {
			let el = list[i];
			if (!el.elements) {
				if (!this._first)
					this._first = el;
				this._last = el;
			}
		}
	}

	_submit() {
	}
}

