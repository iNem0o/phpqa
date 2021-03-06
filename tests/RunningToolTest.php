<?php

namespace Edge\QA;

class RunningToolTest extends \PHPUnit_Framework_TestCase
{
    private $errorsCountInXmlFile = 2;

    public function testBuildOptionWithDefinedSeparator()
    {
        $tool = new RunningTool('tool', ['optionSeparator' => ' ']);
        assertThat($tool->buildOption('option', ''), is('--option'));
        assertThat($tool->buildOption('option', 'value'), is('--option value'));
        assertThat($tool->buildOption('option', 0), is('--option 0'));
    }

    public function testMarkSuccessWhenXPathIsNotDefined()
    {
        $tool = new RunningTool('tool', ['errorsXPath' => null]);
        assertThat($tool->analyzeResult(), is([true, '']));
    }

    public function testMarkFailureWhenXmlFileDoesNotExist()
    {
        $tool = new RunningTool('tool', [
            'xml' => ['non-existent.xml'],
            'errorsXPath' => '//errors/error',
        ]);
        assertThat($tool->analyzeResult(), is([false, 0]));
    }

    /** @dataProvider provideAllowedErrors */
    public function testCompareAllowedCountWithErrorsCountFromXml($allowedErrors, $isOk)
    {
        $tool = new RunningTool('tool', [
            'xml' => ['tests/Error/errors.xml'],
            'errorsXPath' => '//errors/error',
            'allowedErrorsCount' => $allowedErrors
        ]);
        assertThat($tool->analyzeResult(), is([$isOk, $this->errorsCountInXmlFile]));
    }

    public function provideAllowedErrors()
    {
        return [
            'success when allowed errors are not defined' => [null, true],
            'success when errors count <= allowed count' => [$this->errorsCountInXmlFile, true],
            'failure when errors count > allowed count' => [$this->errorsCountInXmlFile - 1, false],
        ];
    }

    public function testRuntimeSelectionOfErrorXpath()
    {
        $tool = new RunningTool('tool', [
            'xml' => ['tests/Error/errors.xml'],
            'errorsXPath' => [
                false => '//errors/error',
                true => '//errors/error[@severity="error"]',
            ],
            'allowedErrorsCount' => 0,
        ]);
        $tool->errorsType = true;
        assertThat($tool->analyzeResult(), is([false, 1]));
    }

    /** @dataProvider provideProcess */
    public function testAnalyzeExitCodeInCliMode($allowedErrors, $exitCode, array $expectedResult)
    {
        if (version_compare(PHP_VERSION, '7.2.0') >= 0) {
            $this->markTestSkipped('Skipped on PHP 7.2');
        }
        $tool = new RunningTool('tool', [
            'allowedErrorsCount' => $allowedErrors
        ]);
        $tool->process = $this->prophesize('Symfony\Component\Process\Process')
            ->getExitCode()->willReturn($exitCode)
            ->getObjectProphecy()->reveal();
        assertThat($tool->analyzeResult(true), is($expectedResult));
    }

    public function provideProcess()
    {
        return [
            'success when exit code = 0' => [0, 0, [true, 0]],
            'success when exit code <= allowed code' => [1, 1, [true, 1]],
            'failure when errors count > allowed count but errors count is always one' => [0, 2, [false, 1]],
        ];
    }

    public function testCreateUniqueIdForUserReport()
    {
        $tool = new RunningTool('phpcs', []);
        $tool->userReports['dir/path.php'] = 'My report';
        $report = $tool->getHtmlRootReports()[0];
        assertThat($report['id'], is('phpcs-dir-path-php'));
    }
}
