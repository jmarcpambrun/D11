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

/* themes/contrib/bartik/templates/maintenance-page.html.twig */
class __TwigTemplate_83447d4e140e652d58b47dd9b6306732 extends Template
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
        // line 11
        yield "<div id=\"page-wrapper\">
  <div id=\"page\">
    <header id=\"header\" role=\"banner\">
      <div class=\"section clearfix\">
        ";
        // line 15
        if ((($context["site_name"] ?? null) || ($context["site_slogan"] ?? null))) {
            // line 16
            yield "          <div id=\"name-and-slogan\"";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar((((($context["hide_site_name"] ?? null) && ($context["hide_site_slogan"] ?? null))) ? (" class=\"visually-hidden\"") : ("")));
            yield ">
            ";
            // line 17
            if (($context["site_name"] ?? null)) {
                // line 18
                yield "              <div id=\"site-name\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(((($context["hide_site_name"] ?? null)) ? (" class=\"visually-hidden\"") : ("")));
                yield ">
                <strong>
                  <a href=\"";
                // line 20
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["front_page"] ?? null), "html", null, true);
                yield "\" title=\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Home"));
                yield "\" rel=\"home\"><span>";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["site_name"] ?? null), "html", null, true);
                yield "</span></a>
                </strong>
              </div>
            ";
            }
            // line 24
            yield "            ";
            if (($context["site_slogan"] ?? null)) {
                // line 25
                yield "              <div id=\"site-slogan\"";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(((($context["hide_site_slogan"] ?? null)) ? (" class=\"visually-hidden\"") : ("")));
                yield ">
                ";
                // line 26
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["site_slogan"] ?? null), "html", null, true);
                yield "
              </div>
            ";
            }
            // line 29
            yield "          </div>
        ";
        }
        // line 31
        yield "      </div>
    </header>
    <div id=\"main-wrapper\">
      <div id=\"main\" class=\"clearfix\">
        <main id=\"content\" class=\"column\" role=\"main\">
          <section class=\"section\">
            <a id=\"main-content\"></a>
            ";
        // line 38
        if (($context["title"] ?? null)) {
            // line 39
            yield "              <h1 class=\"title\" id=\"page-title\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title"] ?? null), "html", null, true);
            yield "</h1>
            ";
        }
        // line 41
        yield "            ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 41), "html", null, true);
        yield "
            ";
        // line 42
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 42), "html", null, true);
        yield "
          </section>
        </main>
      </div>
    </div>
  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["site_name", "site_slogan", "hide_site_name", "hide_site_slogan", "front_page", "title", "page"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "themes/contrib/bartik/templates/maintenance-page.html.twig";
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
        return array (  116 => 42,  111 => 41,  105 => 39,  103 => 38,  94 => 31,  90 => 29,  84 => 26,  79 => 25,  76 => 24,  65 => 20,  59 => 18,  57 => 17,  52 => 16,  50 => 15,  44 => 11,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "themes/contrib/bartik/templates/maintenance-page.html.twig", "D:\\xampp_8_2_12\\htdocs\\drupal-10.2.1\\themes\\contrib\\bartik\\templates\\maintenance-page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 15];
        static $filters = ["escape" => 20, "t" => 20];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape', 't'],
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
