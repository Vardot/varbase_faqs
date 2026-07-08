'use strict';

/**
 * @file
 * Extra step definitions for the Varbase FAQ (varbase_faqs) test suite.
 *
 * The suite reuses the shared Varbase step definitions (logging in as a named
 * user, becoming anonymous, opening admin pages) plus the built-in webship-js
 * steps (navigation, "I should see", web-first assertions). The only helper
 * that cannot be expressed with those is creating an FAQ node, because the
 * FAQ content type has its own add form at /node/add/faq with a different field
 * set than the Basic page: the title field is labelled "Question" and the body
 * field is labelled "Answer" and is edited through CKEditor 5 (with a plain
 * textarea fallback when no rich-text editor is assigned to the format).
 */

const { When } = require('@cucumber/cucumber');
const {
  friendly,
  gotoUrl,
  waitForPageLoad,
} = require('webship-js/tests/step-definitions/webship');

/**
 * Run a step body and rethrow any failure as a tester-friendly error.
 *
 * @param {Function} body
 *   Async function performing the step.
 * @param {string} message
 *   Human-readable description for failures.
 */
async function attempt(body, message) {
  try {
    await body();
  }
  catch (err) {
    throw friendly(message, err);
  }
}

/**
 * Create an FAQ node through the /node/add/faq form.
 *
 * Fills the "Question" (title) field and the "Answer" (body) field — using the
 * CKEditor 5 editable when present, otherwise the plain body textarea — then
 * saves. After saving, Varbase's Rabbit Hole behaviour may 301-redirect the
 * faq node's canonical page to /faqs; the feature should therefore assert the
 * creation message and/or the FAQ page listing rather than the node page.
 *
 * Example:
 *   When I create an FAQ node titled "Can I export my data?" answered "Yes, from your account settings."
 */
When(/^(?:I |we )?create an FAQ node titled "([^"]*)" answered "([^"]*)"$/, async function (question, answer) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/node/add/faq`);
    await waitForPageLoad(this.page, (this.minWaitTime && this.minWaitTime.page) || 10000);

    // Question (title) field.
    await this.page.locator('#edit-title-0-value').fill(question);

    // Answer (body) field: CKEditor 5 editable if present, else plain textarea.
    const editable = this.page.locator('.ck-editor__editable[contenteditable="true"]').first();
    if (await editable.count() > 0) {
      await editable.click();
      await editable.fill(answer);
    }
    else {
      await this.page.locator('#edit-body-0-value').fill(answer);
    }

    await this.page.locator('#edit-submit').click();
    await waitForPageLoad(this.page, (this.minWaitTime && this.minWaitTime.page) || 10000);
  }, `Could not create an FAQ node titled "${question}"`);
});
