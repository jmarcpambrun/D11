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

/* themes/contrib/seven/templates/status-report-grouped.html.twig */
class __TwigTemplate_69dc22f60587397ed12a0dabc0b7370d extends Template
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
        // line 19
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("core/drupal.collapse"), "html", null, true);
        yield "
";
        // line 20
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("seven/drupal.responsive-detail"), "html", null, true);
        yield "

<div class=\"system-status-report\">
  ";
        // line 23
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["grouped_requirements"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["group"]) {
            // line 24
            yield "    <div class=\"system-status-report__requirements-group\">
      <h3 id=\"";
            // line 25
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["group"], "type", [], "any", false, false, true, 25), "html", null, true);
            yield "\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["group"], "title", [], "any", false, false, true, 25), "html", null, true);
            yield "</h3>
      ";
            // line 26
            $context['_parent'] = $context;
            $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, $context["group"], "items", [], "any", false, false, true, 26));
            foreach ($context['_seq'] as $context["_key"] => $context["requirement"]) {
                // line 27
                yield "        <details class=\"system-status-report__entry system-status-report__entry--";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["group"], "type", [], "any", false, false, true, 27), "html", null, true);
                yield " color-";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["group"], "type", [], "any", false, false, true, 27), "html", null, true);
                yield "\" open>
          ";
                // line 29
                $context["summary_classes"] = ["system-status-report__status-title", ((CoreExtension::inFilter(CoreExtension::getAttribute($this->env, $this->source,                 // line 31
$context["group"], "type", [], "any", false, false, true, 31), ["warning", "error"])) ? (("system-status-report__status-icon system-status-report__status-icon--" . CoreExtension::getAttribute($this->env, $this->source, $context["group"], "type", [], "any", false, false, true, 31))) : (""))];
                // line 34
                yield "          <summary";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute(["class" => ($context["summary_classes"] ?? null)]), "html", null, true);
                yield " role=\"button\">
            ";
                // line 35
                if (CoreExtension::getAttribute($this->env, $this->source, $context["requirement"], "severity_title", [], "any", false, false, true, 35)) {
                    // line 36
                    yield "              <span class=\"visually-hidden\">";
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["requirement"], "severity_title", [], "any", false, false, true, 36), "html", null, true);
                    yield "</span>
            ";
                }
                // line 38
                yield "            ";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["requirement"], "title", [], "any", false, false, true, 38), "html", null, true);
                yield "
          </summary>
          <div class=\"system-status-report__entry__value\">
            ";
                // line 41
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["requirement"], "value", [], "any", false, false, true, 41), "html", null, true);
                yield "
            ";
                // line 42
                if (CoreExtension::getAttribute($this->env, $this->source, $context["requirement"], "description", [], "any", false, false, true, 42)) {
                    // line 43
                    yield "              <div class=\"description\">";
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["requirement"], "description", [], "any", false, false, true, 43), "html", null, true);
                    yield "</div>
            ";
                }
                // line 45
                yield "          </div>
        </details>
      ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_key'], $context['requirement'], $context['_parent']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 48
            yield "    </div>
  ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_key'], $context['group'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 50
        yield "</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["grouped_requirements"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/contrib/seven/templates/status-report-grouped.html.twig";
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
        return array (  128 => 50,  121 => 48,  113 => 45,  107 => 43,  105 => 42,  101 => 41,  94 => 38,  88 => 36,  86 => 35,  81 => 34,  79 => 31,  78 => 29,  71 => 27,  67 => 26,  61 => 25,  58 => 24,  54 => 23,  48 => 20,  44 => 19,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/contrib/seven/templates/status-report-grouped.html.twig", "D:\\xampp_8_2_12\\htdocs\\drupal-10.2.1\\themes\\contrib\\seven\\templates\\status-report-grouped.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["for" => 23, "set" => 29, "if" => 35];
        static $filters = ["escape" => 19];
        static $functions = ["attach_library" => 19, "create_attribute" => 34];

        try {
            $this->sandbox->checkSecurity(
                ['for', 'set', 'if'],
                ['escape'],
                ['attach_library', 'create_attribute'],
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
