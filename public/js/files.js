/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2024 Aleksey Andreev (liuch)
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

class Files {
	constructor() {
		this._container   = null;
		this._fieldset1   = null;
		this._element     = document.getElementById("main-block");
		this._fcount_info = null;
		this._fsize_info  = null;
		this._limits      = {
			upload_max_file_count: 0,
			upload_max_file_size:  0
		};
		this._directories = {};
		[ "email", "local", "remote" ].forEach(type => {
			this._directories[type] = { list: [], element: null, table: null };
		});
	}

	display() {
		this._create_container();
		this._create_local_file_uploading_element();
		this._container.appendChild(this._fieldset1);
		if (User.level === "admin") {
			this._create_directory_loading_element(
				this._directories.email,
				"email",
				"Loading reports files from the mailboxes",
				"No mailboxes are configured."
			);
			this._container.appendChild(this._directories.email.element);
			this._create_directory_loading_element(
				this._directories.local,
				"local",
				"Loading report files from the server directory",
				"No directories are configured."
			);
			this._container.appendChild(this._directories.local.element);
			this._create_directory_loading_element(
				this._directories.remote,
				"remote",
				"Loading report files from the remote filesystem",
				"No remote filesystems are configured."
			);
			this._container.appendChild(this._directories.remote.element);
		}
		this._element.appendChild(this._container);
		this._fieldset1.focus();
	}

	update() {
		if (!Status.instance().error()) {
			let admin = User.level === "admin";
			this._fetch_data(true, admin, admin, admin);
		}
	}

	title() {
		return "Report Files";
	}

	_create_container() {
		this._container = document.createElement("div");
		this._container.setAttribute("class", "panel-container round-border");
	}

	_create_local_file_uploading_element() {
		this._fieldset1 = document.createElement("fieldset");
		this._fieldset1.setAttribute("class", "round-border");
		this._fieldset1.disabled = true;
		let lg = document.createElement("legend");
		lg.appendChild(document.createTextNode("Uploading local report files"));
		this._fieldset1.appendChild(lg);
		let fm = document.createElement("form");
		fm.setAttribute("enctype", "multipart/form-data");
		fm.setAttribute("method", "post");
		fm.appendChild(this._create_input_element("hidden", "cmd", "upload-report"));
		let fl = this._create_input_element("file", "report_file[]", null)
		fl.required = true;
		fl.multiple = true;
		fm.appendChild(fl);
		let dv = document.createElement("div");
		dv.setAttribute("class", "buttons-block");
		let sb = this._create_button_element("submit", "Upload reports");
		sb.disabled = true;
		dv.appendChild(sb);
		dv.appendChild(this._create_button_element("reset", "Reset"));
		fm.appendChild(dv);
		let that = this;
		fl.addEventListener("change", function(event) {
			sb.disabled = !that._check_files(fl);
		});
		fm.addEventListener("reset", function(event) {
			sb.disabled = true;
			that._clear_warnings();
		});
		fm.addEventListener("submit", function(event) {
			window.fetch("files.php", {
				method: "POST",
				credentials: "same-origin",
				body: new FormData(fm)
			}).then(function(resp) {
				if (!resp.ok)
					throw new Error("Failed to upload a report file");
				return resp.json();
			}).then(function(data) {
				Common.checkResult(data);
				Notification.add({ text: (data.message || "Uploaded successfully!"), type: "info" });
				Status.instance().update().catch(() => {});
			}).catch(function(err) {
				Common.displayError(err);
				Notification.add({ text: (err.message || "Error!"), type: "error" });
			});
			event.preventDefault();
			fm.reset();
		});
		this._fieldset1.appendChild(fm);
	}

	_create_directory_loading_element(dir, type, title, no_message) {
		dir.element = document.createElement("fieldset");
		dir.element.setAttribute("class", "round-border");
		dir.element.disabled = true;
		dir.element.appendChild(document.createElement("legend")).textContent = title;

		let fm = document.createElement("form");
		fm.method = "post";
		dir.table = new ITable({
			class:   "main-table subtable",
			onclick: function(row) {
				let userdata = row.userdata();
				let checkbox = row.element().querySelector("input");
				if (checkbox && !userdata.error) {
					userdata.checked = !userdata.checked;
					checkbox.checked = userdata.checked;
					this._update_directory_button(dir);
				}
			}.bind(this),
			nodata_text: no_message
		});
		[
			{ content: "", class: "cell-status" },
			{ content: "Name" }
		].forEach(function(col) {
			dir.table.add_column(col);
		}, this);
		if (type === "email") {
			dir.table.add_column({ content: "Host" });
			dir.table.add_column({ content: "Mailbox" });
		} else {
			dir.table.add_column({ content: "Reports" });
			dir.table.add_column({ content: "Location" });
		}
		fm.appendChild(dir.table.element());
		let bb = document.createElement("div");
		bb.setAttribute("class", "buttons-block");
		fm.appendChild(bb);
		let sb = this._create_button_element("submit", "Load reports");
		sb.disabled = true;
		bb.appendChild(sb);

		fm.addEventListener("submit", function(event) {
			sb.disabled = true;
			let ids = dir.list.filter(function(it) {
				return it.checked;
			}).map(function(it) {
				return it.id;
			});
			let cmd = null;
			switch (type) {
				case "email":
					cmd = "load-mailbox";
					break;
				case "local":
					cmd = "load-directory";
					break;
				case "remote":
					cmd = "load-remotefs";
					break;
			}
			let that = this;
			window.fetch("files.php", {
				method: "POST",
				headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
				credentials: "same-origin",
				body: JSON.stringify({ cmd: cmd, ids: ids })
			}).then(function(resp) {
				if (!resp.ok)
					throw new Error("Failed to load report files");
				return resp.json();
			}).then(function(data) {
				if (!data.error_code) {
					Notification.add({ text: (data.message || "Loaded successfully!"), type: "info" });
				}
				if (data.other_errors) {
					that._notify_other_errors(data.other_errors);
				}
				Common.checkResult(data);
				Status.instance().update().catch(() => {});
			}).catch(function(err) {
				Common.displayError(err);
				Notification.add({ text: (err.message || "Error!"), type: "error" });
			}).finally(function() {
				that._fetch_data(false, type === "email", type === "local", type === "remote");
			});
			event.preventDefault();
		}.bind(this));

		dir.element.appendChild(fm);
	}

	_display_files_info() {
		this._fcount_info = document.createElement("div");
		this._fcount_info.classList.add("state-gray");
		let dv = this._fcount_info.appendChild(document.createElement("div"));
		dv.classList.add("state-text");
		dv.append("You can upload not more than " + this._limits.upload_max_file_count + " files.");

		this._fsize_info = document.createElement("div");
		this._fsize_info.classList.add("state-gray");
		dv = this._fsize_info.appendChild(document.createElement("div"));
		dv.classList.add("state-text");
		dv.append("You can upload a file with no more than " + bytes2size(this._limits.upload_max_file_size) + ".");

		dv = this._fieldset1.appendChild(document.createElement("div"));
		dv.classList.add("info-block");
		dv.append(this._fcount_info, this._fsize_info);
	}

	_update_directory_loading_element(dir) {
		dir.table.clear();
		let d = {};
		d.rows = dir.list.map(function(it) {
			it.checked = false;
			let rd = {
				cells: [
					new DirectoryCheckboxCell(it.error ? null : false),
					{ content: it.name }
				],
				userdata: it
			};
			if (it.error) {
				rd.class = "state-red";
				rd.cells.push({ content: "Error!", class: "state-text" });
			} else {
				if (it.files !== undefined) {
					rd.cells.push({ content: it.files });
				} else {
					rd.cells.push({ content: it.host });
				}
			}
			rd.cells.push({ content: it.location || it.mailbox });
			return rd;
		});
		dir.table.add_frame(new ITableFrame(d, dir.table.last_row_index() + 1));
	}

	_update_directory_button(item) {
		item.element.querySelector("button[type=submit]").disabled = !item.list.some(function(it) {
			return it.checked;
		});
	}

	_clear_warnings() {
		[ this._fcount_info, this._fsize_info ].forEach(function(el) {
			if (el) {
				el.classList.remove("state-red");
				el.classList.add("state-gray");
			}
		});
	}

	_notify_other_errors(errors) {
		let cut = null;
		let length = errors.length;
		if (length > 4) {
			cut = errors.slice(0, 3);
			cut.push("and " + (length - 3) + " more errors");
		}
		Notification.add({ text: cut || errors, type: "error" });
	}

	_set_warning(el) {
		if (el) {
			el.classList.remove("state-gray");
			el.classList.add("state-red");
		}
	}

	_check_files(fl_el) {
		this._clear_warnings();

		if (fl_el.files.length == 0) {
			return false;
		}

		let res = true;
		if (fl_el.files.length > this._limits.upload_max_file_count) {
			res = false;
			this._set_warning(this._fcount_info);
			let message = "You can only upload " + this._limits.upload_max_file_count + " files.";
			Notification.add({ type: "error", text: message, delay: 10000, name: "max-files" });
		}

		let bf_cnt = 0;
		for (let i = 0; i < fl_el.files.length; ++i) {
			if (fl_el.files[i].size > this._limits.upload_max_file_size) {
				++bf_cnt;
			}
		};
		if (bf_cnt > 0) {
			res = false;
			this._set_warning(this._fsize_info);
			Notification.add({
				type: "error",
				text: "" + bf_cnt + " file" + (bf_cnt > 1 && "s" || "") + " exceed the maximum allowed size.",
				delay: 10000,
				name: "max-size"
			});
		}

		return res;
	}

	_create_button_element(type, text) {
		let el = document.createElement("button");
		el.setAttribute("type", type);
		el.appendChild(document.createTextNode(text));
		return el;
	}

	_create_input_element(type, name, value) {
		let el = document.createElement("input");
		el.setAttribute("type", type);
		if (name)
			el.setAttribute("name", name);
		if (value)
			el.setAttribute("value", value);
		return el;
	}

	_fetch_data(files, ems, dirs, rfs) {
		if (files) {
			this._fieldset1.disabled = true;
			this._fieldset1.insertBefore(set_wait_status(), this._fieldset1.children[0]);
		}
		let dmap = new Map();
		if (ems) dmap.set("mailboxes", this._directories.email);
		if (dirs) dmap.set("directories", this._directories.local);
		if (rfs) dmap.set("remotefs", this._directories.remote);
		for (let dir of dmap.values()) {
			let el = dir.element;
			el.disabled = true;
			el.insertBefore(set_wait_status(), el.children[0]);
		}
		let that = this;
		window.fetch("files.php", {
			method: "GET",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (!resp.ok)
				throw new Error("Failed to get loader data");
			return resp.json();
		}).then(function(data) {
			Common.checkResult(data);
			if (files) {
				that._limits.upload_max_file_count = data.upload_max_file_count;
				that._limits.upload_max_file_size  = data.upload_max_file_size;
				that._display_files_info();
				that._fieldset1.disabled = false;
			}
			for (let key of dmap.keys()) {
				let dir = dmap.get(key);
				dir.list = data[key] || [];
				that._update_directory_loading_element(dir);
				dir.element.disabled = false;
			}
		}).catch(function(err) {
			Common.displayError(err);
			Notification.add({ type: "error", text: err.message });
			if (files) {
				that._fieldset1.insertBefore(set_error_status(null, err.message), that._fieldset1.children[0]);
			}
			for (let dir of dmap.values()) {
				dir.element.insertBefore(set_error_status(null, err.message), dir.element.children[0]);
			}
		}).finally(function() {
			if (files) {
				that._fieldset1.querySelector(".wait-message").remove();
			}
			for (let dir of dmap.values()) {
				dir.element.querySelector(".wait-message").remove();
			}
		});
	}
}

class DirectoryCheckboxCell extends ITableCell {
	value(target) {
		if (target === "dom") {
			let cb = document.createElement("input");
			cb.setAttribute("type", "checkbox");
			if (this._content !== null) {
				cb.checked = this._content;
			}
			else {
				cb.disabled = true;
				cb.checked  = false;
			}
			return cb;
		}
		return this._content;
	}
}
