/**
 * dmarc-srg - A php parser, viewer and summary report generator for incoming DMARC reports.
 * Copyright (C) 2020-2025 Aleksey Andreev (liuch)
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

class LoginDialog extends VerticalDialog {
	constructor(params) {
		super();
		this._params = params || {};
		this._params.buttons = [ "ok", "cancel" ];
		this._params.title = "Authentication";
		this._params.overlay_click = "ignore";
		this._user   = null;
		this._pass   = null;
	}

	remove() {
		if (this._element) {
			this._element.remove();
			this._element = null;
		}
	}

	_gen_content() {
		if (!this._params.nousername) {
			this._user = this._insert_input_row("User name", "text", "Enter your user name");
		}
		this._pass = this._insert_input_row("Password", "password", "Enter your password");
		this.display_status("wait", "Enter your credentials");
	}

	_insert_input_row(text, type, placeholder) {
		let inp = document.createElement("input");
		inp.required = true;
		inp.setAttribute("type", type);
		inp.setAttribute("placeholder", placeholder);
		super._insert_input_row(text, inp);
		return inp;
	}

	_enable_elements(enable) {
		this._buttons[0].disabled = !enable;
		for (const el of this._element.querySelector("form").elements) {
			el.disabled = !enable;
		}
	}

	_submit() {
		this._buttons[1].focus();
		this._enable_elements(false);
		let body = {};
		if (!this._params.nousername) {
			body.username = this._user.value;
		}
		body.password = this._pass.value;
		if (this._params.nofetch) {
			this._result = body;
			this.hide();
			return;
		}
		let hide = false;
		this.display_status("wait", "Sending credentials to the server...");
		window.fetch("login.php", {
			method: "POST",
			cache: "no-store",
			headers: Object.assign(HTTP_HEADERS, HTTP_HEADERS_POST),
			credentials: "same-origin",
			body: JSON.stringify(body)
		}).then(resp => {
			if (!resp.ok) throw new Error("Failed to log in");
			return resp.json();
		}).then(data => {
			Common.checkResult(data);
			this._result = data;
			Notification.add({ type: "info", text: data.message || "Successfully!", name: "auth" });
			hide = true;
		}).catch(err => {
			this._pass.value = "";
			Common.displayError(err);
			this.display_status("error", err.message);
		}).finally(() => {
			this._enable_elements(true);
			if (hide) {
				this.hide();
			} else {
				this.focus();
			}
		});
	}
}

LoginDialog.start = function (params) {
	if (User.auth_type !== "base") {
		params ||= {};
		if (params.nousername === undefined) params.nousername = true;
	}
	let login = new LoginDialog(params);
	document.getElementById("main-block").appendChild(login.element());
	login.show().then(function(d) {
		if (d) {
			Router.go();
		}
	}).catch(function(err) {
		Common.displayError(err);
	}).finally(function() {
		login.remove();
		login = null;
	});
};
