<?php
/**
 * LoginBar.php
 *
 * Shows the login bar.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $Id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */

/**
 * @var UserAccessManager\Controller\FrontendController $oController
 */
if ($oController->showLoginForm()) {
    ?>
    <form action="<?php echo $oController->getLoginUrl(); ?>" method="post" class="uam_login_form">
        <label class="input_label" for="user_login"><?php echo TXT_UAM_LOGIN_FORM_USERNAME; ?>:</label>
        <input name="log" value="<?php echo $oController->getUserLogin(); ?>" class="input" id="user_login"
               type="text"/>
        <label class="input_label" for="user_pass"><?php echo TXT_UAM_LOGIN_FORM_PASSWORD; ?>:</label>
        <input name="pwd" class="input" id="user_pass" type="password"/>
        <input name="rememberme" class="checkbox" id="rememberme" value="forever" type="checkbox"/>
        <label class="checkbox_label" for="rememberme">
            <?php echo TXT_UAM_LOGIN_FORM_REMEMBER_ME; ?>
        </label>
        <input class="button" type="submit" name="wp-submit" id="wp-submit"
               value="<?php echo TXT_UAM_LOGIN_FORM_LOGIN; ?> &raquo;"/>
        <input type="hidden" name="redirect_to" value="'.$oController->getRequestUrl().'"/>

    </form>';
    <div class="uam_login_options">
        <?php
        if (get_option('users_can_register')) {
            ?>
            <a href="<?php echo $oController->getLoginUrl(); ?>/wp-login.php?action=register">
                <?php echo TXT_UAM_LOGIN_FORM_REGISTER; ?>
            </a>
            <?php
        }
        ?>

        <a href="<?php echo $oController->getLoginUrl(); ?>/wp-login.php?action=lostpassword"
           title="<?php echo TXT_UAM_LOGIN_FORM_LOST_AND_FOUND_PASSWORD; ?>">
            <?php echo TXT_UAM_LOGIN_FORM_LOST_PASSWORD; ?>
        </a>';
    </div>
    <?php
} else {
    ?>
    <a class="uam_login_link" href="<?php echo $oController->getRedirectLoginUrl(); ?>">
        <?php TXT_UAM_LOGIN_FORM_LOGIN; ?>
    </a>
    <?php
}