<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* core/modules/locale/templates/locale-translation-update-info.html.twig */
class __TwigTemplate_8086e27af9e7738bc75f33ee9e34ebaa extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 18
        yield "<div class=\"locale-translation-update__wrapper\" tabindex=\"0\" role=\"button\">
  <span class=\"locale-translation-update__prefix visually-hidden\">Show description</span>
  ";
        // line 20
        if (($context["modules"] ?? null)) {
            // line 21
            yield "    ";
            $context["module_list"] = $this->extensions['Drupal\Core\Template\TwigExtension']->safeJoin($this->env, ($context["modules"] ?? null), ", ");
            // line 22
            yield "    <span class=\"locale-translation-update__message\">";
            yield t("Updates for: @module_list", array("@module_list" => ($context["module_list"] ?? null), ));
            yield "</span>
  ";
        } elseif (        // line 23
($context["not_found"] ?? null)) {
            // line 24
            yield "    <span class=\"locale-translation-update__message\">";
            // line 25
            yield \Drupal::translation()->formatPlural(abs(Twig\Extension\CoreExtension::length($this->env->getCharset(),             // line 27
($context["not_found"] ?? null))), "Missing translations for one project", "Missing translations for @count projects", array());
            // line 30
            yield "</span>
  ";
        }
        // line 32
        yield "  ";
        if ((($context["updates"] ?? null) || ($context["not_found"] ?? null))) {
            // line 33
            yield "    <div class=\"locale-translation-update__details\">
      ";
            // line 34
            if (($context["updates"] ?? null)) {
                // line 35
                yield "        <ul>
          ";
                // line 36
                $context['_parent'] = $context;
                $context['_seq'] = CoreExtension::ensureTraversable(($context["updates"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["update"]) {
                    // line 37
                    yield "            <li>";
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["update"], "name", [], "any", false, false, true, 37), "html", null, true);
                    yield " (";
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->env->getFilter('format_date')->getCallable()(CoreExtension::getAttribute($this->env, $this->source, $context["update"], "timestamp", [], "any", false, false, true, 37), "html_date"), "html", null, true);
                    yield ")</li>
          ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_key'], $context['update'], $context['_parent']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 39
                yield "        </ul>
      ";
            }
            // line 41
            yield "      ";
            if (($context["not_found"] ?? null)) {
                // line 42
                yield "        ";
                // line 46
                yield "        ";
                if (($context["updates"] ?? null)) {
                    // line 47
                    yield "          ";
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Missing translations for:"));
                    yield "
        ";
                }
                // line 49
                yield "        ";
                if (($context["not_found"] ?? null)) {
                    // line 50
                    yield "          <ul>
            ";
                    // line 51
                    $context['_parent'] = $context;
                    $context['_seq'] = CoreExtension::ensureTraversable(($context["not_found"] ?? null));
                    foreach ($context['_seq'] as $context["_key"] => $context["update"]) {
                        // line 52
                        yield "              <li>";
                        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["update"], "name", [], "any", false, false, true, 52), "html", null, true);
                        yield " (";
                        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ((CoreExtension::getAttribute($this->env, $this->source, $context["update"], "version", [], "any", true, true, true, 52)) ? (Twig\Extension\CoreExtension::default(CoreExtension::getAttribute($this->env, $this->source, $context["update"], "version", [], "any", false, false, true, 52), t("no version"))) : (t("no version"))), "html", null, true);
                        yield "). ";
                        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["update"], "info", [], "any", false, false, true, 52), "html", null, true);
                        yield "</li>
            ";
                    }
                    $_parent = $context['_parent'];
                    unset($context['_seq'], $context['_key'], $context['update'], $context['_parent']);
                    $context = array_intersect_key($context, $_parent) + $_parent;
                    // line 54
                    yield "          </ul>
        ";
                }
                // line 56
                yield "      ";
            }
            // line 57
            yield "    </div>
  ";
        }
        // line 59
        yield "</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["modules", "not_found", "updates"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/modules/locale/templates/locale-translation-update-info.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  147 => 59,  143 => 57,  140 => 56,  136 => 54,  123 => 52,  119 => 51,  116 => 50,  113 => 49,  107 => 47,  104 => 46,  102 => 42,  99 => 41,  95 => 39,  84 => 37,  80 => 36,  77 => 35,  75 => 34,  72 => 33,  69 => 32,  65 => 30,  63 => 27,  62 => 25,  60 => 24,  58 => 23,  53 => 22,  50 => 21,  48 => 20,  44 => 18,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/modules/locale/templates/locale-translation-update-info.html.twig", "D:\\xampp_8_2_12\\htdocs\\drupal-10.2.1\\core\\modules\\locale\\templates\\locale-translation-update-info.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 20, "set" => 21, "trans" => 22, "for" => 36];
        static $filters = ["safe_join" => 21, "escape" => 22, "length" => 27, "format_date" => 37, "t" => 47, "default" => 52];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'set', 'trans', 'for'],
                ['safe_join', 'escape', 'length', 'format_date', 't', 'default'],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
