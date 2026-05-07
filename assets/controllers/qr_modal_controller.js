import { Controller } from "@hotwired/stimulus";
import QRCodeStyling from "qr-code-styling"

export default class extends Controller {
    static targets = ["container"];
    static values = { uuid: String, logo: String};

    connect() {
        const qr = new QRCodeStyling({
            width: 200,
            height: 200,
            type: "svg",
            data: this.uuidValue,
            image: this.logoValue,
            imageOptions: { margin: 6 },
        })
        qr.append(this.containerTarget);
    }
}