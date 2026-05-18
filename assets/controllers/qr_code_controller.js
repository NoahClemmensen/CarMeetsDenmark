import { Controller } from "@hotwired/stimulus";
import QRCodeStyling from "qr-code-styling"

/**
 * Renders a QR code into the container target encoding the `data` value.
 *
 * Usage:
 *   <div data-controller="qr-code"
 *        data-qr-code-data-value="https://example.com/share/abc">
 *     <div data-qr-code-target="container"></div>
 *   </div>
 */
export default class extends Controller {
    static targets = ["container"];
    static values = { data: String, logo: String };

    connect() {
        const qr = new QRCodeStyling({
            width: 200,
            height: 200,
            type: "svg",
            data: this.dataValue,
            image: this.logoValue,
            imageOptions: { margin: 6 },
        });
        qr.append(this.containerTarget);
    }
}
