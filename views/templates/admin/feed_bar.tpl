{*
* NOTICE OF LICENSE
*
* This source file is subject to a commercial license from DataFeedWatch
* Use, copy, modification or distribution of this source file without written
* license agreement from the MigrationPro is strictly forbidden.
* In order to obtain a license, please contact us: contact@prestapros.com
*
* @author    PrestaPros.com
* @copyright Copyright (c) 2017-2021 PrestaPros
* @license   Commercial license
* @package   datafeedwatch
*}
<form class="defaultForm form-horizontal">
    <div class="panel" id="fieldset_0">
        <div class="panel-heading">
            {l s='Feed Url' mod='datafeedwatch'}
        </div>
        <div class="form-wrapper">
            <div class="form-group">
                {$example_url|escape:'htmlall':'UTF-8'}
            </div>
            <div class="form-group">
                <a style="background-color: #00aff0; color: white;" class="btn-default btn" target="_blank" href="{$button_url|escape:'htmlall':'UTF-8'}" >
                    {l s='Go to DataFeedWatch' mod='datafeedwatch'}
                </a>
            </div>
        </div>
    </div>
</form>
