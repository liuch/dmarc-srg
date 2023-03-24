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

class Notification {
	constructor(params) {
		this._params = params;
		this._element = this._create_element();
	}

	element() {
		return this._element;
	}

	_create_element() {
		let el = document.createElement("div");
		el.setAttribute("class", "notification");
		if (this._params.type === "error")
			el.classList.add("notif-error");
		else if (this._params.type === "warn")
			el.classList.add("notif-warn");
		else
			el.classList.add("notif-info");
		{
			let text = this._params.text;
			if (typeof(text) !== "object")
				text = [ text ];
			for (let i = 0; ; ) {
				el.appendChild(document.createTextNode(text[i]));
				++i;
				if (i == text.length)
					break;
				el.appendChild(document.createElement("br"));
			}
		}
		let btn = document.createElement("button");
		btn.setAttribute("type", "button");
		btn.setAttribute("class", "notif-close");
		btn.appendChild(document.createTextNode("x"));
		el.appendChild(btn);
		el.addEventListener("click", function(event) {
			if (event.target.classList.contains("notif-close"))
				this.remove();
		});
		if (this._params.delay > 0) {
			setTimeout(function() {
				el.style.transition = "opacity 2s ease-in-out";
				el.style.opacity = 0;
				setTimeout(function() { el.remove(); }, 2000);
			}, this._params.delay);
		}
		return el;
	}
}

Notification.add = function(params) {
	for (let key in Notification.defaults) {
		if (params[key] === undefined)
			params[key] = Notification.defaults[key];
	}
	let notif = new Notification(params);
	document.getElementById("notifications-block").appendChild(notif.element());
	return notif;
}

Notification.defaults = {
	type: "info",
	delay: 5000
};

