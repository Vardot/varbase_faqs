<?php

/**
 * @file
 * Contains varbase_faqs_update_9###(s) hook updates.
 */

use Vardot\Installer\ModuleInstallerFactory;

/**
 * Issue #3272287: Switch to use the Module Installer Factory.
 *
 * In the Varbase FAQs module.
 */
function varbase_faqs_update_90001() {
  // Set the weight of the module after installation of list of modules.
  // To make sure that any hook or event subscriber workers after all used modules.
  ModuleInstallerFactory::setModuleWeightAfterInstallation('varbase_faqs');
}
