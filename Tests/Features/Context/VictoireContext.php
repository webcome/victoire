<?php

namespace Victoire\Tests\Features\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\Element;
use Behat\Mink\Session;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Knp\FriendlyContexts\Context\RawMinkContext;
use Symfony\Component\Finder\Finder;

/**
 * This class gives some usefull methods for Victoire navigation.
 *
 * @property MinkContext minkContext
 */
class VictoireContext extends RawMinkContext
{
    use KernelDictionary;
    protected $minkContext;

    /**
     * @BeforeSuite
     *
     * @param BeforeSuiteScope $scope
     */
    public static function additionalContexts(BeforeSuiteScope $scope)
    {
        $environment = $scope->getEnvironment();
        $contextDir = __DIR__.'/../../../../../../Tests/Context/';

        if (!is_dir($contextDir)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($contextDir)->name('*Context.php');

        foreach ($finder as $file) {
            $path = $file->getRealPath();
            include $path;
            $declaredClases = get_declared_classes();
            $newContext = end($declaredClases);
            $environment->registerContextClass($newContext);
        }
    }

    /**
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();
        $this->minkContext = $environment->getContext('Victoire\Tests\Features\Context\MinkContext');
    }

    /**
     * @AfterBackground
     *
     * @param BeforeStepScope $scope
     */
    public function resetViewsReference(BeforeStepScope $scope)
    {
        $viewsReferences = $this->getContainer()->get('victoire_core.view_helper')->buildViewsReferences();
        $this->getContainer()->get('victoire_view_reference.manager')->saveReferences($viewsReferences);
    }

    /**
     * @AfterStep
     *
     * @param AfterStepScope $scope
     *
     * @throws \Exception
     */
    public function lookForJSErrors(AfterStepScope $scope)
    {
        /* @var Session $session */
        $session = $this->getSession();

        if (!($session->getDriver() instanceof Selenium2Driver)) {
            return;
        }

        try {
            $errors = $session->evaluateScript('window.jsErrors');
            $session->evaluateScript('window.jsErrors = []');
        } catch (\Exception $e) {
            throw $e;
        }
        if (!$errors || empty($errors)) {
            return;
        }
        $file = sprintf('%s:%d', $scope->getFeature()->getFile(), $scope->getStep()->getLine());
        $message = sprintf('Found %d javascript error%s', count($errors), count($errors) > 0 ? 's' : '');
        echo '-------------------------------------------------------------'.PHP_EOL;
        echo $file.PHP_EOL;
        echo $message.PHP_EOL;
        echo '-------------------------------------------------------------'.PHP_EOL;
        foreach ($errors as $index => $error) {
            echo sprintf('   #%d: %s', $index, $error).PHP_EOL;
        }
    }

    /**
     * @Given I am logged in as :email
     */
    public function iAmLoggedInAsUser($email)
    {
        $this->minkContext->visit('/login');
        $this->minkContext->fillField('username', $email);
        $this->minkContext->fillField('password', 'test');
        $this->minkContext->pressButton('_submit');
    }

    /**
     * @Given I login as visitor
     */
    public function iLoginAsVisitor()
    {
        $this->getSession()->getDriver()->stop();
        $baseUrl = $this->minkContext->getMinkParameter('base_url');
        $url = str_replace('anakin@victoire.io:test', 'z6po@victoire.io:test', $baseUrl);
        $this->minkContext->setMinkParameter('base_url', $url);
    }

    /**
     * @Given /^I visit homepage through domain "([^"]*)"$/
     */
    public function ivisitHomepageThroughDomain($domain)
    {
        $this->getSession()->getDriver()->stop();
        $url = sprintf('http://z6po@victoire.io:test@%s:8000/app_domain.php', $domain);
        $this->minkContext->setMinkParameter('base_url', $url);
        $this->minkContext->visitPath('/');
    }

    /**
     * @Then /^I fill in wysiwyg with "([^"]*)"$/
     */
    public function iFillInWysiwygOnFieldWith($arg)
    {
        $js = 'CKEDITOR.instances.victoire_widget_form_ckeditor_content.setData("'.$arg.'");';
        $this->getSession()->executeScript($js);
    }

    /**
     * @Then /^I select "([^"]*)" from the "([^"]*)" select of "([^"]*)" slot$/
     */
    public function iSelectFromTheSelectOfSlot($widget, $nth, $slot)
    {
        $slot = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::*[contains(@id, "vic-slot-'.$slot.'")]');
        $selects = $slot->findAll('css', 'select[role="menu"]');
        $selects[$nth - 1]->selectOption(str_replace('\\"', '"', $widget));
    }

    /**
     * @Then /^I switch to "([^"]*)" mode$/
     */
    public function iSwitchToMode($mode)
    {
        $element = $this->findOrRetry($this->getSession()->getPage(), 'xpath', 'descendant-or-self::*[@for="mode-switcher--'.$mode.'"]');

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @Then /^I (open|close|toggle) the hamburger menu$/
     */
    public function iOpenTheHamburgerMenu()
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'xpath',
            'descendant-or-self::*[@id="vic-menu-leftnavbar-trigger"]'
        );

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I open the widget mode drop for entity :entity
     */
    public function iOpenTheWidgetModeDrop($entity)
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'css',
            '[id^="picker-'.strtolower($entity).'"] .v-mode-trigger'
        );
        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I open the widget style tab :key
     */
    public function iOpenTheWidgetStyleTab($key)
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'css',
            '[title="style-'.$key.'"]'
        );
        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I follow the float action button
     */
    public function iFollowTheFloatAction()
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'css',
            '#v-float-container [data-flag="v-drop v-drop-fab"]'
        );
        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I open the widget quantum collapse for entity :entity
     */
    public function iOpenTheWidgetQuantumCollapse($entity)
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'css',
            '[id^="picker-'.strtolower($entity).'"][data-state="visible"] [id^="picker-'.strtolower($entity).'"][data-state="visible"] .v-widget-form__quantum-btn'
        );

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I open the widget quantum collapse when static
     */
    public function iOpenTheWidgetQuantumCollapseWhenStatic()
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'css',
            '[data-state="visible"] [id^="picker-static"] .v-widget-form__quantum-btn'
        );

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @Then /^I open the settings menu$/
     */
    public function iOpenTheSettingsMenu()
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'xpath',
            'descendant-or-self::*[@id="v-settings-link"]'
        );

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @Then /^I open the additional menu drop$/
     */
    public function iOpenTheAdditionalsMenuDrop()
    {
        $element = $this->findOrRetry(
            $this->getSession()->getPage(),
            'xpath',
            'descendant-or-self::*[@id="v-additionals-drop"]'
        );

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I follow the tab :name
     */
    public function iFollowTheTab($name)
    {
        $element = $this->findOrRetry($this->getSession()->getPage(), 'xpath', sprintf('descendant-or-self::a[contains(@class, "v-tabs-nav__anchor") and contains(normalize-space(text()), "%s")]', $name));

        // @TODO When the new styleguide is completly integrated, remove.
        if (null === $element) {
            $element = $this->findOrRetry($this->getSession()->getPage(), 'xpath', sprintf('descendant-or-self::a[@data-toggle="vic-tab" and normalize-space(text()) = "%s"]', $name));
        }

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I follow the drop trigger :name
     */
    public function iFollowTheDropTrigger($name)
    {
        $element = $this->findOrRetry($this->getSession()->getPage(), 'xpath', sprintf('descendant-or-self::a[@data-flag*="v-drop" and normalize-space(text()) = "%s"]', $name));

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
        $element->click();
    }

    /**
     * @When I follow the drop anchor :name
     */
    public function iFollowTheDropAnchor($name)
    {
        $page = $this->getSession()->getPage();
        $elements = $page->findAll('xpath', sprintf('descendant-or-self::a[contains(@class, "v-drop__anchor") and normalize-space(text()) = "%s"]', $name));

        if (count($elements) < 1) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }

        foreach ($elements as $element) {
            if ($element->getText() === $name) {
                $element->click();
            }
        }
    }

    /**
     * @Then /^I submit the widget$/
     * @Then /^I submit the modal$/
     */
    public function iSubmitTheWidget()
    {
        $element = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::a[@data-modal="create"]');

        if (!$element) {
            $element = $this->getSession()->getPage()->find('xpath', 'descendant-or-self::a[@data-modal="update"]');
        }
        $element->click();
        $this->getSession()->wait(2000);
    }

    /**
     * @Given /^I edit an "([^"]*)" widget$/
     * @Given /^I edit the "([^"]*)" widget$/
     */
    public function iEditTheWidget($widgetType)
    {
        $selector = sprintf('.v-widget--%s > a.v-widget__overlay', strtolower($widgetType));
        $session = $this->getSession(); // get the mink session
        $element = $this->findOrRetry($session->getPage(), 'css', $selector);

        // errors must not pass silently
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS selector: "%s"', $selector));
        }

        // ok, let's hover it
        $element->mouseOver();
        $element->click();
    }

    /**
     * @Then /^"([^"]*)" should precede "([^"]*)"$/
     */
    public function shouldPrecedeForTheQuery($textBefore, $textAfter)
    {
        $element = $this->getSession()->getPage()->find(
            'xpath',
            sprintf('//*[normalize-space(text()) = "%s"][preceding::*[normalize-space(text()) = "%s"]]',
                $textAfter,
                $textBefore
            )
        );
        if (null === $element) {
            $message = sprintf('"%s" does not preceed "%s"', $textBefore, $textAfter);

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
    }

    /**
     * @When /^I select the option "(?P<option>[^"]*)" in the dropdown "(?P<dropdown>[^"]*)"$/
     */
    public function iSelectTheOptionInTheDropdown($option, $dropdown)
    {
        $link = $this->getSession()->getPage()->find('css', sprintf('a.vic-dropdown-toggle[title="%s"]', $dropdown));
        $link->click();
        $optionButton = $this->getSession()->getPage()->find('css', sprintf('ul[aria-labelledby="%sDropdownMenu"] > li > a[title="%s"]', $dropdown, $option));
        $optionButton->click();
    }

    /**
     * @Then /^I attach image with id "(\d+)" to victoire field "(.+)"$/
     */
    public function attachImageToVictoireScript($imageId, $fieldId)
    {
        $script = sprintf('$("#%s input").val(%d)', $fieldId, $imageId);
        $this->getSession()->executeScript($script);
    }

    /**
     * @Then I should find css element :element with selector :selector and value :value
     */
    public function iShouldFindCssWithSelectorAndValue($element, $selector, $value)
    {
        $css = sprintf('%s[%s="%s"]', $element, $selector, $value);
        $session = $this->getSession();
        $element = $this->findOrRetry($session->getPage(), 'css', $css);

        if (null === $element) {
            $message = sprintf('Element not found. String generate: %s[%s="%s"]', $element, $selector, $value);

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
    }

    /**
     * @Then I should see disable drop anchor :name
     */
    public function iShouldSeeDisableDropAnchor($name)
    {
        $element = $this->findOrRetry($this->getSession()->getPage(), 'xpath', sprintf('descendant-or-self::*[contains(@class, \'v-drop__anchor--disabled\') and normalize-space(.) = "%s"]', $name));

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
    }

    /**
     * @Then I should see disable tab :name
     */
    public function iShouldSeeDisableTab($name)
    {
        $element = $this->findOrRetry($this->getSession()->getPage(), 'xpath', sprintf('descendant-or-self::li[@class="vic-disable" and normalize-space(.) = "%s"]', $name));

        if (null === $element) {
            $message = sprintf('Element not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
    }

    /**
     * @Then /^I move the widgetMap "(.+)" "(.+)" the widgetMap "(.*)"$/
     */
    public function iMoveWidgetUnder($widgetMapMoved, $position, $widgetMapMovedTo)
    {
        if (!$widgetMapMovedTo) {
            $widgetMapMovedTo = 'null';
        }
        $js = 'updateWidgetPosition({"parentWidgetMap": '.$widgetMapMovedTo.', "slot": "main_content", "position": "'.$position.'", "widgetMap": '.$widgetMapMoved.'})';

        $this->getSession()->executeScript($js);
    }

    /**
     * @When /^I rename quantum "(.+)" with "(.+)"$/
     */
    public function iRenameQuantumWith($quantumPosition, $name)
    {
        $session = $this->getSession();

        $pencilSelector = sprintf('descendant-or-self::ul[contains(@class, \'vic-quantum-nav\')]/li[%s]/a/i[contains(@class, \'fa-pencil\')]', $quantumPosition);
        $pencil = $this->findOrRetry($session->getPage(), 'xpath', $pencilSelector);
        $pencil->click();

        $input = $this->findOrRetry($session->getPage(), 'css', '.quantum-edit-field');
        $input->setValue($name);

        //Click outside
        $list = $this->findOrRetry($session->getPage(), 'css', '.vic-quantum-nav');
        $list->click();
    }

    /**
     * @When /^I select quantum "(.+)"$/
     */
    public function iSelectQuantum($quantumName)
    {
        $session = $this->getSession();

        $quantumSelector = sprintf('descendant-or-self::a[contains(@class, \'v-btn--quantum\') and normalize-space(.) = "%s"]', $quantumName);
        $quantum = $this->findOrRetry($session->getPage(), 'xpath', $quantumSelector);
        $quantum->click();
    }

    /**
     * @When /^I create a new quantum$/
     */
    public function iCreateANewQuantum()
    {
        $session = $this->getSession();

        $element = $this->findOrRetry($session->getPage(), 'css', '#widget-new-tab');
        $element->click();
    }

    /**
     * @When /^I should see "(.+)" quantum$/
     * @When /^I should see "(.+)" quantum creation button$/
     */
    public function iShouldSeeXQuantum($nb)
    {
        $session = $this->getSession();

        $quantums = $this->findOrRetry(
            $session->getPage(),
            'xpath',
            'descendant-or-self::div[contains(@id, "v-quantum-tab")]/descendant-or-self::a[contains(@class, "v-btn--quantum")]',
            10000,
            'findAll'
        );

        if (count($quantums) != $nb) {
            $message = sprintf('%s quantum(s) found', count($quantums));

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }
    }

    /**
     * @When /^I should see the success message for Widget edit$/
     */
    public function iShouldSeeTheSuccessMessageForWidgetEdit()
    {
        $this->minkContext->assertPageContainsText('Victoire!');
    }

    /**
     * @When I select :arg1 from the collapse menu
     */
    public function iSelectFromTheCollapseMenu($name)
    {
        $page = $this->getSession()->getPage();

        $menus = $page->findAll('xpath', sprintf('descendant-or-self::a[contains(@class, "v-mode-trigger")]'));
        if (count($menus) < 1) {
            $message = sprintf('Collapse menu not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }

        foreach ($menus as $menu) {
            if ($menu->isVisible()) {
                $menu->click();
            }
        }

        $links = $menu = $page->findAll('xpath', sprintf('descendant-or-self::div[contains(@class, "v-drop__menu")]//a[contains(@class, "v-drop__anchor") and normalize-space(text()) = "%s"]', $name));

        if (count($links) < 1) {
            $message = sprintf('Menu link not found in the page after 10 seconds"');

            throw new \Behat\Mink\Exception\ResponseTextException($message, $this->getSession());
        }

        foreach ($links as $link) {
            if ($link->getText() === $name) {
                $link->click();
            }
        }
    }

    /**
     * Try to find value in element and retry for a given time.
     *
     * @param Element $element
     * @param string  $selectorType xpath|css
     * @param string  $value
     * @param int     $timeout
     * @param string  $method
     *
     * @return \Behat\Mink\Element\NodeElement|mixed|null|void
     */
    protected function findOrRetry(Element $element, $selectorType, $value, $timeout = 10000, $method = 'find')
    {
        if ($timeout <= 0) {
            return;
        }

        $item = $element->$method($selectorType, $value);

        if ($item) {
            return $item;
        } else {
            $this->getSession()->wait(100);

            return $this->findOrRetry($element, $selectorType, $value, $timeout - 100);
        }
    }
}
