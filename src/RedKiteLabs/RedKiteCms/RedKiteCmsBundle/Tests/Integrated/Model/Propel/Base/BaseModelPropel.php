<?php
/**
 * This file is part of the RedKite CMS Application and it is distributed
 * under the MIT License. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) RedKite Labs <webmaster@redkite-labs.com>
 *
 * For the full copyright and license infpageRepositoryation, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.redkite-labs.com
 *
 * @license    MIT License
 *
 */

namespace RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Tests\Integrated\Model\Propel\Base;

use RedKiteLabs\RedKiteCms\RedKiteCmsBundle\Tests\WebTestCaseFunctional;
/**
 * BaseModelPropel
 *
 * @author RedKite Labs <webmaster@redkite-labs.com>
 */
abstract class BaseModelPropel extends WebTestCaseFunctional
{
    public static function setUpBeforeClass()
    {
        self::$languages = array(
            array('LanguageName'      => 'en'),
            array('LanguageName'      => 'es',)
        );

        self::$pages = array(
            array('PageName'      => 'index',
                  'TemplateName'  => 'home',
                  'IsHome'        => '1',
                  'Permalink'     => 'this is a website fake page',
                  'MetaTitle'         => 'page title',
                  'MetaDescription'   => 'page description',
                  'MetaKeywords'      => 'key'),
            array('PageName'      => 'page1',
                  'TemplateName'  => 'empty',
                  'Permalink'     => 'page-1',
                  'MetaTitle'         => 'page 1 title',
                  'MetaDescription'   => 'page 1 description',
                  'MetaKeywords'      => '')

            );
        self::populateDb();
    }
}