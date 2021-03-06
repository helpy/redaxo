/**
 * REDAXO Visual Regression testing
 *
 * 1. Start a local php-server with `php -S localhost:8000` from within the project root
 * 2. Make sure a database server is running
 * 3. Make sure a admin-user with login `myusername` and password `mypassword` exists
 * 4. Make sure the REDAXO instance running at START_URL is accessible and login screen appears on the url
 * 5. Start the visual recording with `node .github/tests-visual/visual-record.js`
 */

const puppeteer = require('puppeteer');
const pixelmatch = require('pixelmatch');
const PNG = require('pngjs').PNG;
const fs = require('fs');
const mkdirp = require('mkdirp');

const screenshotWidth = 1280;
const screenshotHeight = 1024;

const START_URL = 'http://localhost:8000/redaxo/index.php';
const DEBUGGING = false;
const WORKING_DIR = '.tests-visual/';
const GOLDEN_SAMPLES_DIR = '.github/tests-visual/';

const myArgs = process.argv.slice(2);
let minDiffPixels = 1;
let isSetup = false;

if (myArgs.includes('regenerate-all')) {
    // force sample-regeneration, even if pixelmatch() thinks nothing changed
    minDiffPixels = 0;
}
if (myArgs.includes('setup')) {
    isSetup = true;
}
const MIN_DIFF_PIXELS = minDiffPixels;

// htaccess ajax checks are subject to race conditions and therefore generated 'random' markup.
// disable the check to get less visual noise.
const noHtaccessCheckCookie = {
    name: 'rex_htaccess_check',
    value: '1',
    domain: 'localhost',
    httpOnly: false,
    secure: false
};

// all pages
const allPages = {
    'structure_category_edit.png': START_URL + '?page=structure&category_id=0&article_id=0&clang=1&edit_id=1&function=edit_cat&catstart=0',
    'structure_article_edit.png': START_URL + '?page=content/edit&category_id=1&article_id=1&clang=1&mode=edit',
    'structure_article_functions.png': START_URL + '?page=content/functions&article_id=1&category_id=1&clang=1&ctype=1',
    'structure_slice_edit.png': START_URL + '?page=content/edit&article_id=1&slice_id=3&clang=1&ctype=1&function=edit#slice3',

    'mediapool_media.png': START_URL + '?page=mediapool/media',
    'mediapool_upload.png': START_URL + '?page=mediapool/upload',
    'mediapool_structure.png': START_URL + '?page=mediapool/structure',
    'mediapool_sync.png': START_URL + '?page=mediapool/sync',

    'templates.png': START_URL + '?page=templates',
    'templates_add.png': START_URL + '?page=templates&function=add',
    'templates_edit.png': START_URL + '?page=templates&function=edit&template_id=1',

    'modules_modules.png': START_URL + '?page=modules/modules',
    'modules_modules_add.png': START_URL + '?page=modules/modules&function=add',
    'modules_actions.png': START_URL + '?page=modules/actions',
    'modules_actions_add.png': START_URL + '?page=modules/actions&function=add',

    'users_users.png': START_URL + '?page=users/users',
    'users_edit.png': START_URL + '?page=users/users&user_id=1',
    'users_roles.png': START_URL + '?page=users/roles',
    'users_role_add.png': START_URL + '?page=users/roles&func=add&default_value=1',

    'packages.png': START_URL + '?page=packages',

    'system_settings.png': START_URL + '?page=system/settings',
    'system_lang.png': START_URL + '?page=system/lang',
    'system_log.png': START_URL + '?page=system/log/redaxo',
    'system_report.png': START_URL + '?page=system/report/html',

    'backup_export.png': START_URL + '?page=backup/export',
    'backup_import.png': START_URL + '?page=backup/import',
    'backup_import_server.png': START_URL + '?page=backup/import/server',

    'cronjob_cronjobs.png': START_URL + '?page=cronjob/cronjobs',
    'cronjob_cronjobs_add.png': START_URL + '?page=cronjob/cronjobs&func=add',

    'media_manager_types.png': START_URL + '?page=media_manager/types',
    'media_manager_types_add.png': START_URL + '?page=media_manager/types&func=add',
    'media_manager_types_edit.png': START_URL + '?page=media_manager/types&type_id=1&effects=1',
    'media_manager_settings.png': START_URL + '?page=media_manager/settings',

    'metainfo_articles.png': START_URL + '?page=metainfo/articles',
    'metainfo_articles_add.png': START_URL + '?page=metainfo/articles&func=add',
    'metainfo_categories.png': START_URL + '?page=metainfo/categories',
    'metainfo_media.png': START_URL + '?page=metainfo/media',
    'metainfo_clangs.png': START_URL + '?page=metainfo/clangs',

    'phpmailer_config.png': START_URL + '?page=phpmailer/config',
};

function countDiffPixels(img1path, img2path ) {
    if (!fs.existsSync(img2path)) {
        // no reference image
        // we assume a new reference screenshot will be added
        return MIN_DIFF_PIXELS;
    }

    const img1 = PNG.sync.read(fs.readFileSync(img1path));
    const img2 = PNG.sync.read(fs.readFileSync(img2path));

    return pixelmatch(img1.data, img2.data, null, screenshotWidth, screenshotHeight, {threshold: 0.1});
}

async function createScreenshot(page, screenshotName) {
    mkdirp.sync(WORKING_DIR);

    // mask dynamic content, to make it not appear like change (visual noise)
    await page.evaluate(function() {
        var changingElements = [
            '.rex-js-script-time',
            'td[data-title="Letzter Login"]',
            '#rex-form-exportfilename',
            '#rex-page-system-report-html .row td',
            'td[data-title="Version"]',
            'td[data-title="Erstellt am"]'
        ];

        changingElements.forEach(function (selector) {
            var els = document.querySelectorAll(selector);

            els.forEach(function (el) {
                if (el) {
                    el.innerHTML = 'XXX';
                    el.value = 'XXX'; // handle input elements
                }
            });
        });
    });

    await page.screenshot({ path: WORKING_DIR + screenshotName });

    // make sure we only create changes in .github/tests-visual/ on substential screenshot changes.
    // this makes sure to prevent endless loops within the github action
    let diffPixels = countDiffPixels(WORKING_DIR + screenshotName, GOLDEN_SAMPLES_DIR + screenshotName);
    console.log("DIFF-PIXELS: "+ screenshotName + ":" +diffPixels);
    if (diffPixels >= MIN_DIFF_PIXELS) {
        fs.renameSync(WORKING_DIR + screenshotName, GOLDEN_SAMPLES_DIR + screenshotName);
    }
}

async function main() {
    const options = { args: ['--no-sandbox', '--disable-setuid-sandbox'] };

    if (DEBUGGING) {
        // see https://developers.google.com/web/tools/puppeteer/debugging
        options.headless = false;
    }

    const browser = await puppeteer.launch(options);
    let page = await browser.newPage();
    // log browser errors into the console
    page.on('console', msg => console.log('BROWSER-CONSOLE:', msg.text()));

    await page.setViewport({ width: screenshotWidth, height: screenshotHeight });
    await page.setCookie(noHtaccessCheckCookie);

    if (isSetup) {

        // setup step 1
        await page.goto(START_URL, { waitUntil: 'load' });
        await createScreenshot(page, 'setup.png');

        // setup steps 2-7
        for (var step = 2; step <= 7; step++) {
            await page.goto(START_URL + '?page=setup&lang=de_de&step=' + step, { waitUntil: 'load' });
            await createScreenshot(page, 'setup_' + step + '.png');
        }

    } else {

        // login page
        await page.goto(START_URL, { waitUntil: 'load' });
        await page.waitForTimeout(1000); // CSS animation
        await createScreenshot(page, 'login.png');

        // login successful
        await page.type('#rex-id-login-user', 'myusername');
        await page.type('#rex-id-login-password', '91dfd9ddb4198affc5c194cd8ce6d338fde470e2'); // sha1('mypassword')
        await page.$eval('#rex-form-login', form => form.submit());
        await page.waitForTimeout(1000);
        await createScreenshot(page, 'index.png');

        // run through all pages
        for (var fileName in allPages) {
            await page.goto(allPages[fileName], { waitUntil: 'load' });
            await page.waitForTimeout(300); // CSS animation
            await createScreenshot(page, fileName);
        }
    }

    await page.close();
    await browser.close();
}

main();
