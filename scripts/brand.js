const path = require( 'path' );
const fs = require( 'fs' );
const ROOT = path.join( __dirname, '..' );
const { chromium } = require( path.join( ROOT, 'node_modules/@playwright/test' ) );

const shot = 'data:image/png;base64,' + fs.readFileSync( path.join( ROOT, '.wordpress-org/screenshot-1.png' ) ).toString( 'base64' );
const shieldIcon = ( size, color ) => `<svg width="${ size }" height="${ size }" viewBox="0 0 24 24"><path fill="${ color }" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>`;
const bg = 'background:linear-gradient(135deg,#0a0f1d 0%,#0f1f3d 50%,#1e40af 100%);';
const font = "font-family:-apple-system,'Segoe UI',Inter,Helvetica,Arial,sans-serif;";
const previewShot = ( w, h ) => `<div style="width:${ w }px;height:${ h }px;border-radius:${ Math.round( h / 14 ) }px;overflow:hidden;box-shadow:0 ${ h / 25 }px ${ h / 8 }px rgba(0,0,0,.5);background:url(${ shot }) 50% 50% / 100% auto no-repeat #f8fafc"></div>`;

const banner = ( w, h ) => `<div style="${ bg }${ font }width:${ w }px;height:${ h }px;display:flex;align-items:center;justify-content:space-between;padding:0 ${ w * 0.06 }px;box-sizing:border-box;color:#fff">
	<div>
		<div style="display:flex;align-items:center;gap:${ h * 0.05 }px">
			${ shieldIcon( h * 0.22, '#38bdf8' ) }
			<div style="font-size:${ h * 0.13 }px;font-weight:700;letter-spacing:-.5px;white-space:nowrap">Login Shield</div>
		</div>
		<div style="font-size:${ h * 0.06 }px;opacity:.92;margin-top:${ h * 0.04 }px;color:#e2e8f0">Custom Login URL, Brute Force Protection & Anti-Lockout Shield</div>
	</div>
	${ previewShot( w * 0.38, h * 0.76 ) }
</div>`;

const items = [
	[ 'icon-256x256.png', 256, 256, `<div style="${ bg }width:256px;height:256px;display:flex;align-items:center;justify-content:center">${ shieldIcon( 160, '#38bdf8' ) }</div>` ],
	[ 'icon-128x128.png', 128, 128, `<div style="${ bg }width:128px;height:128px;display:flex;align-items:center;justify-content:center">${ shieldIcon( 80, '#38bdf8' ) }</div>` ],
	[ 'banner-772x250.png', 772, 250, banner( 772, 250 ) ],
	[ 'banner-1544x500.png', 1544, 500, banner( 1544, 500 ) ],
	[ '../.github/media/social-preview.png', 1280, 640, banner( 1280, 640 ) ],
];

( async () => {
	const browser = await chromium.launch( { channel: 'chrome' } );
	for ( const [ name, w, h, html ] of items ) {
		const page = await browser.newPage( { viewport: { width: w, height: h } } );
		await page.setContent( `<body style="margin:0">${ html }</body>` );
		await page.screenshot( { path: path.join( ROOT, '.wordpress-org', name ) } );
		await page.close();
	}
	await browser.close();
	console.log( 'Generated icons, banners and social-preview.' );
} )();
