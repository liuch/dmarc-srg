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

class Files {
	constructor(id) {
		this._container   = null;
		this._fieldset    = null;
		this._fcount_info = 0;
		this._fsize_info  = 0;
		this._limits      = {
			upload_max_file_count: 0,
			upload_max_file_size:  0
		};
	}

	display() {
		let mcn = document.getElementById("main-block");
		remove_all_children(mcn);
		this._create_container();
		this._create_local_file_uploading_element();
		this._container.appendChild(this._fieldset);
		mcn.appendChild(this._container);
	}

	update() {
		this._fieldset.disabled = true;
		if (!Status.instance().error()) {
			this._fetch_data();
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
		this._fieldset = document.createElement("fieldset");
		this._fieldset.setAttribute("class", "round-border");
		let lg = document.createElement("legend");
		lg.appendChild(document.createTextNode("Uploading local report files"));
		this._fieldset.appendChild(lg);
		let fm = document.createElement("form");
		fm.setAttribute("enctype", "multipart/form-data");
		fm.setAttribute("method", "post");
		fm.appendChild(this._create_input_element("hidden", "cmd", "upload-report"));
		let fl = this._create_input_element("file", "report_file[]", null)
		fl.required = true;
		fl.multiple = true;
		fm.appendChild(fl);
		let dv = document.createElement("div");
		let sb = this._create_input_element("submit", null, "Upload reports");
		sb.disabled = true;
		dv.appendChild(sb);
		dv.appendChild(this._create_input_element("reset", null, "Reset"));
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
			let fd = new FormData(fm);
			window.fetch("files.php", {
				method: "POST",
				headers: HTTP_HEADERS,
				credentials: "same-origin",
				body: fd
			}).then(function(resp) {
				if (resp.status !== 200) {
					throw new Error("Failed to upload a report file");
				}
				return resp.json();
			}).then(function(data) {
				if (data.error_code !== undefined && data.error_code !== 0)
					Notification.add({ text: (data.message || "Error!"), type: "error" });
				else
					Notification.add({ text: (data.message || "Uploaded successfully!"), type: "info" });
			}).catch(function(err) {
				Notification.add({ text: (err.message || "Error!"), type: "error" });
			});
			event.preventDefault();
			fm.reset();
		});
		this._fieldset.appendChild(fm);
	}

	_display_files_info() {
		this._fcount_info = document.createElement("div");
		this._fcount_info.setAttribute("class", "state-gray");
		let dv = document.createElement("div");
		dv.setAttribute("class", "state-text");
		dv.appendChild(
			document.createTextNode(
				"You can upload not more than " + this._limits.upload_max_file_count + " files."
			)
		);
		this._fcount_info.appendChild(dv);

		this._fsize_info = document.createElement("div");
		this._fsize_info.setAttribute("class", "state-gray");
		dv = document.createElement("div");
		dv.setAttribute("class", "state-text");
		dv.appendChild(
			document.createTextNode(
				"You can upload a file with no more than " + bytes2size(this._limits.upload_max_file_size) + "."
			)
		);
		this._fsize_info.appendChild(dv);

		dv = document.createElement("div");
		dv.setAttribute("class", "info-block");
		dv.appendChild(this._fcount_info);
		dv.appendChild(this._fsize_info);
		this._fieldset.appendChild(dv);
	}

	_clear_warnings() {
		[ this._fcount_info, this._fsize_info ].forEach(function(el) {
			el.classList.remove("state-red");
			el.classList.add("state-gray");
		});
	}

	_set_warning(el) {
		el.classList.remove("state-gray");
		el.classList.add("state-red");
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
			Notification.add({ type: "error", text: message, delay: 10000 });
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
				delay: 10000
			});
		}

		return res;
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

	_fetch_data() {
		this._fieldset.insertBefore(set_wait_status(), this._fieldset.children[0]);
		let that = this;
		window.fetch("files.php", {
			method: "GET",
			headers: HTTP_HEADERS,
			credentials: "same-origin"
		}).then(function(resp) {
			if (resp.status !== 200) {
				throw new Error("Failed to get loader data");
			}
			return resp.json();
		}).then(function(data) {
			if (data.error_code !== undefined && data.error_code !== 0) {
				throw new Error(data.message || "Unknown error");
			}
			that._limits.upload_max_file_count = data.upload_max_file_count;
			that._limits.upload_max_file_size  = data.upload_max_file_size;
			that._display_files_info();
			that._fieldset.disabled = false;
		}).catch(function(err) {
			console.warn(err.message);
			Notification.add({ type: "error", text: err.message });
			that._fieldset.insertBefore(set_error_status(null, err.message), that._fieldset.children[0]);
		}).finally(function() {
			that._fieldset.querySelector(".wait-message").remove();
		});
	}
}

