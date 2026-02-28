<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductResourceFormTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function product_resource_form_method_exists_and_is_callable()
    {
        $this->assertTrue(method_exists(ProductResource::class, 'form'));
        $this->assertTrue(is_callable([ProductResource::class, 'form']));
    }

    /** @test */
    public function product_resource_form_does_not_have_syntax_errors()
    {
        // This test will fail if there are PHP syntax errors in the form method
        $reflection = new \ReflectionClass(ProductResource::class);
        $formMethod = $reflection->getMethod('form');

        $this->assertTrue($formMethod->isStatic());
        $this->assertTrue($formMethod->isPublic());
    }

    /** @test */
    public function product_resource_form_has_correct_method_signature()
    {
        $reflection = new \ReflectionClass(ProductResource::class);
        $method = $reflection->getMethod('form');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters, 'form method should have exactly 1 parameter');
        $this->assertEquals('Filament\Forms\Form', $parameters[0]->getType()->getName(),
            'form method parameter should be of type Filament\Forms\Form');
    }

    /** @test */
    public function product_resource_form_has_schema_method()
    {
        // Test that the form method contains schema() call by checking the method body
        $reflection = new \ReflectionClass(ProductResource::class);
        $method = $reflection->getMethod('form');

        // Get the file content and check if schema() is called
        $fileContent = file_get_contents($reflection->getFileName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $methodContent = '';
        $lines = explode("\n", $fileContent);
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $methodContent .= $lines[$i] . "\n";
        }

        $this->assertStringContainsString('schema(', $methodContent,
            'form method should contain schema() call');
    }

    /** @test */
    public function product_resource_form_contains_expected_components()
    {
        // Check that the form method contains expected Filament components
        $reflection = new \ReflectionClass(ProductResource::class);
        $method = $reflection->getMethod('form');

        $fileContent = file_get_contents($reflection->getFileName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $methodContent = '';
        $lines = explode("\n", $fileContent);
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $methodContent .= $lines[$i] . "\n";
        }

        // Check for common Filament components
        $expectedComponents = [
            'TextInput::make(\'sku\')',
            'Fieldset::make(\'Form Product\')',
            'TextInput::make(\'name\')',
        ];

        foreach ($expectedComponents as $component) {
            $this->assertStringContainsString($component, $methodContent,
                "form method should contain component: {$component}");
        }
    }

    /** @test */
    public function product_resource_form_structure_is_valid()
    {
        // Test that the form method has proper PHP structure
        $reflection = new \ReflectionClass(ProductResource::class);
        $method = $reflection->getMethod('form');

        $fileContent = file_get_contents($reflection->getFileName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $methodContent = '';
        $lines = explode("\n", $fileContent);
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $methodContent .= $lines[$i] . "\n";
        }

        // Check for proper structure
        $this->assertStringContainsString('return $form', $methodContent,
            'form method should return the form instance');
        $this->assertStringContainsString('->schema([', $methodContent,
            'form method should have schema array');

        // Check for balanced brackets (basic syntax check)
        $openBrackets = substr_count($methodContent, '[');
        $closeBrackets = substr_count($methodContent, ']');
        $this->assertEquals($openBrackets, $closeBrackets,
            'form method should have balanced brackets');

        $openParens = substr_count($methodContent, '(');
        $closeParens = substr_count($methodContent, ')');
        $this->assertEquals($openParens, $closeParens,
            'form method should have balanced parentheses');
    }

    /** @test */
    public function product_resource_form_validation_rules_are_present()
    {
        // Check that validation rules are present in the form
        $reflection = new \ReflectionClass(ProductResource::class);
        $method = $reflection->getMethod('form');

        $fileContent = file_get_contents($reflection->getFileName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $methodContent = '';
        $lines = explode("\n", $fileContent);
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $methodContent .= $lines[$i] . "\n";
        }

        // Check for validation-related methods
        $validationMethods = [
            'required',
            'unique',
            'max',
        ];

        $hasValidation = false;
        foreach ($validationMethods as $method) {
            if (str_contains($methodContent, $method)) {
                $hasValidation = true;
                break;
            }
        }

        $this->assertTrue($hasValidation,
            'form method should contain validation rules');
    }

    /** @test */
    public function product_resource_form_labels_are_present()
    {
        // Check that labels are present in the form
        $reflection = new \ReflectionClass(ProductResource::class);
        $method = $reflection->getMethod('form');

        $fileContent = file_get_contents($reflection->getFileName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $methodContent = '';
        $lines = explode("\n", $fileContent);
        for ($i = $startLine - 1; $i < $endLine; $i++) {
            $methodContent .= $lines[$i] . "\n";
        }

        $this->assertStringContainsString('->label(', $methodContent,
            'form method should contain label definitions');
    }
}