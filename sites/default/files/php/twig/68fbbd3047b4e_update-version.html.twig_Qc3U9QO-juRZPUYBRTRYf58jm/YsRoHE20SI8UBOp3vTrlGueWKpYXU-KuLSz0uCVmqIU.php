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

/* core/modules/update/templates/update-version.html.twig */
class __TwigTemplate_2db9c869caef2131a94a845b093e6afa extends Template
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
        // line 28
        yield "<div class=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["attributes"] ?? null), "class", [], "any", false, false, true, 28), "html", null, true);
        yield " project-update__version\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->withoutFilter(($context["attributes"] ?? null), "class"), "html", null, true);
        yield ">
  <div class=\"clearfix\">
    <div class=\"project-update__version-title layout-column layout-column--quarter\">";
        // line 30
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title"] ?? null), "html", null, true);
        yield "</div>
    <div class=\"project-update__version-details layout-column layout-column--quarter\">
      <a href=\"";
        // line 32
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["version"] ?? null), "release_link", [], "any", false, false, true, 32), "html", null, true);
        yield "\">";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["version"] ?? null), "version", [], "any", false, false, true, 32), "html", null, true);
        yield "</a>
      <span class=\"project-update__version-date\">(";
        // line 33
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Twig\Extension\CoreExtension']->formatDate(CoreExtension::getAttribute($this->env, $this->source, ($context["version"] ?? null), "date", [], "any", false, false, true, 33), "Y-M-d"), "html", null, true);
        yield ")</span>
    </div>
    <div class=\"layout-column layout-column--half\">
      <ul class=\"project-update__version-links\">
        <li class=\"project-update__release-notes-link\">
          <a href=\"";
        // line 38
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["version"] ?? null), "release_link", [], "any", false, false, true, 38), "html", null, true);
        yield "\">";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Release notes"));
        yield "</a>
        </li>
        ";
        // line 40
        if (($context["core_compatibility_details"] ?? null)) {
            // line 41
            yield "          <li class=\"project-update__compatibility-details\">
            ";
            // line 42
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["core_compatibility_details"] ?? null), "html", null, true);
            yield "
          </li>
        ";
        }
        // line 45
        yield "      </ul>
    </div>
  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["attributes", "title", "version", "core_compatibility_details"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/modules/update/templates/update-version.html.twig";
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
        return array (  89 => 45,  83 => 42,  80 => 41,  78 => 40,  71 => 38,  63 => 33,  57 => 32,  52 => 30,  44 => 28,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/modules/update/templates/update-version.html.twig", "D:\\xampp_8_2_12\\htdocs\\drupal-10.2.1\\core\\modules\\update\\templates\\update-version.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 40];
        static $filters = ["escape" => 28, "without" => 28, "date" => 33, "t" => 38];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape', 'without', 'date', 't'],
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
