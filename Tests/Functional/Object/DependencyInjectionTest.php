<?php
namespace TYPO3\Flow\Tests\Functional\Object;

/*                                                                        *
 * This script belongs to the TYPO3 Flow framework.                       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Tests\FunctionalTestCase;

/**
 * Functional tests for the Dependency Injection features
 *
 */
class DependencyInjectionTest extends FunctionalTestCase {

	/**
	 * @var ConfigurationManager
	 */
	protected $configurationManager;

	public function setUp() {
		parent::setUp();

		$this->configurationManager = $this->objectManager->get('TYPO3\Flow\Configuration\ConfigurationManager');
	}

	/**
	 * @test
	 */
	public function singletonObjectsCanBeInjectedIntoConstructorsOfSingletonObjects() {
		$objectA = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassA');
		$objectB = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassB');

		$this->assertSame($objectB, $objectA->getObjectB());
	}

	/**
	 * @test
	 */
	public function constructorInjectionCanHandleCombinationsOfRequiredAutowiredAndOptionalArguments() {
		$objectC = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassC');

		// Note: The "requiredArgument" and "thirdOptionalArgument" are defined in the Objects.yaml of the Flow package (testing context)
		$this->assertSame('this is required', $objectC->requiredArgument);
		$this->assertEquals(array('thisIs' => array('anArray' => 'asProperty')), $objectC->thirdOptionalArgument);
	}

	/**
	 * @test
	 */
	public function propertiesOfVariousPrimitiveTypeAreSetInSingletonPropertiesIfConfigured() {
		$objectC = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassC');

		// Note: The arguments are defined in the Objects.yaml of the Flow package (testing context)
		$this->assertSame('a defined string', $objectC->getProtectedStringPropertySetViaObjectsYaml());
		$this->assertSame(42.101010, $objectC->getProtectedFloatPropertySetViaObjectsYaml());
		$this->assertSame(array('iAm' => array('aConfigured' => 'arrayValue')), $objectC->getProtectedArrayPropertySetViaObjectsYaml());
		$this->assertTrue($objectC->getProtectedBooleanTruePropertySetViaObjectsYaml());
		$this->assertFalse($objectC->getProtectedBooleanFalsePropertySetViaObjectsYaml());
	}

	/**
	 * @test
	 */
	public function propertiesAreReinjectedIfTheObjectIsUnserialized() {
		$className = 'TYPO3\Flow\Tests\Functional\Object\Fixtures\PrototypeClassA';

		$singletonA = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassA');

		$prototypeA = unserialize('O:' . strlen($className) . ':"' . $className . '":0:{};');
		$this->assertSame($singletonA, $prototypeA->getSingletonA());
	}

	/**
	 * @test
	 */
	public function virtualObjectsDefinedInObjectsYamlCanUseAFactoryForTheirActualImplementation() {
		$prototypeA = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\PrototypeClassAishInterface');

		$this->assertInstanceOf('TYPO3\Flow\Tests\Functional\Object\Fixtures\PrototypeClassA', $prototypeA);
		$this->assertSame('value defined in Objects.yaml', $prototypeA->getSomeProperty());
	}

	/**
	 * @test
	 */
	public function constructorInjectionInSingletonCanHandleArgumentDefinedInSettings() {
		$objectC = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassC');

		// Note: The "settingsArgument" is defined in the Settings.yaml of the Flow package (testing context)
		$this->assertSame('setting injected singleton value', $objectC->settingsArgument);
	}

	/**
	 * @test
	 */
	public function singletonCanHandleInjectedPrototypeWithSettingArgument() {
		$objectD = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassD');

		// Note: The "settingsArgument" is defined in the Settings.yaml of the Flow package (testing context)
		$this->assertSame('setting injected property value', $objectD->prototypeClassC->settingsArgument);
	}

	/**
	 * @test
	 */
	public function singletonCanHandleInjectedPrototypeWithCustomFactory() {
		$objectD = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassD');

		// Note: The "prototypeClassA" is defined with a custom factory in the Objects.yaml of the Flow package (testing context)
		$this->assertNotNull($objectD->prototypeClassA);
		$this->assertSame('value defined in Objects.yaml', $objectD->prototypeClassA->getSomeProperty());
	}

	/**
	 * @test
	 */
	public function singletonCanHandleConstructorArgumentWithCustomFactory() {
		$objectG = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassG');

		// Note: The "prototypeClassA" is defined with a custom factory in the Objects.yaml of the Flow package (testing context)
		$this->assertNotNull($objectG->prototypeA);
		$this->assertSame('Constructor injection with factory', $objectG->prototypeA->getSomeProperty());
	}

	/**
	 * @test
	 */
	public function onCreationOfObjectInjectionInParentClassIsDoneOnlyOnce() {
		$prototypeDsub = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\PrototypeClassDsub');
		$this->assertSame(1, $prototypeDsub->injectionRuns);
	}

	/**
	 * See http://forge.typo3.org/issues/43659
	 *
	 * @test
	 */
	public function injectedPropertiesAreAvailableInInitializeObjectEvenIfTheClassHasBeenExtended() {
		$prototypeDsub = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\PrototypeClassDsub');
		$this->assertFalse($prototypeDsub->injectedPropertyWasUnavailable);
	}

	/**
	 * @test
	 */
	public function constructorsOfSingletonObjectsAcceptNullArguments() {
		$objectF = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassF');

		$this->assertNull($objectF->getNullValue());
	}

	/**
	 * @test
	 */
	public function constructorsOfPrototypeObjectsAcceptNullArguments() {
		$objectE = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\PrototypeClassE', NULL);

		$this->assertNull($objectE->getNullValue());
	}

	/**
	 * @test
	 */
	public function injectionOfObjectFromSameNamespace() {
		$nonNamespacedDependencies = new Fixtures\ClassWithNonNamespacedDependencies();
		$classB = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SingletonClassB');
		$this->assertSame($classB, $nonNamespacedDependencies->getSingletonClassB());
	}

	/**
	 * @test
	 */
	public function injectionOfObjectFromSubNamespace() {
		$nonNamespacedDependencies = new Fixtures\ClassWithNonNamespacedDependencies();
		$aClassFromSubNamespace = $this->objectManager->get('TYPO3\Flow\Tests\Functional\Object\Fixtures\SubNamespace\AnotherClass');
		$this->assertSame($aClassFromSubNamespace, $nonNamespacedDependencies->getClassFromSubNamespace());
	}

	/**
	 * @test
	 */
	public function injectionOfAllSettings() {
		$classWithSettings = new Fixtures\ClassWithSettings();
		$actualSettings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow');
		$this->assertSame($actualSettings, $classWithSettings->getSettings());
	}


	/**
	 * @test
	 */
	public function injectionOfSpecifiedPackageSettings() {
		$classWithSettings = new Fixtures\ClassWithSettings();

		$actualSettings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow');
		$this->assertSame($actualSettings, $classWithSettings->getInjectedSpecifiedPackageSettings());
	}

	/**
	 * @test
	 */
	public function injectionOfCurrentPackageSettings() {
		$classWithSettings = new Fixtures\ClassWithSettings();

		$actualSettings = $this->configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'TYPO3.Flow');
		$this->assertSame($actualSettings, $classWithSettings->getInjectedCurrentPackageSettings());
	}

	/**
	 * @test
	 */
	public function injectionOfNonExistingSettings() {
		$classWithSettings = new Fixtures\ClassWithSettings();
		$this->assertNull($classWithSettings->getNonExistingSetting());
	}

	/**
	 * @test
	 */
	public function injectionOfSingleSettings() {
		$classWithSettings = new Fixtures\ClassWithSettings();
		$this->assertSame('injected setting', $classWithSettings->getInjectedSettingA());
	}

	/**
	 * @test
	 */
	public function injectionOfSingleSettingsFromSpecificPackage() {
		$classWithSettings = new Fixtures\ClassWithSettings();
		$this->assertSame('injected setting', $classWithSettings->getInjectedSettingB());
	}

}
