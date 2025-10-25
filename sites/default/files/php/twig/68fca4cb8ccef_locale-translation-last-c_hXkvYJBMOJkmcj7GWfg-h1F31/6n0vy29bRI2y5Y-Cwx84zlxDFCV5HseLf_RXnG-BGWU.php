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

/* core/modules/locale/templates/locale-translation-last-check.html.twig */
class __TwigTemplate_3678d89f2dc64bb83cab05d783905cf3 extends Template
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
        // line 17
        yield "<div class=\"locale checked\">
  <p>
  ";
        // line 19
        if (($context["last_checked"] ?? null)) {
            // line 20
            yield "    ";
            yield t("Last checked: @time ago", array("@time" => ($context["time"] ?? null), ));
            // line 21
            yield "  ";
        } else {
            // line 22
            yield "    ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Last checked: never"));
            yield "
  ";
        }
        // line 24
        yield "  <span class=\"check-manually\">(";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["link"] ?? null), "html", null, true);
        yield ")</span></p>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["last_checked", "time", "link"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/modules/locale/templates/locale-translation-last-check.html.twig";
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
        return array (  62 => 24,  56 => 22,  53 => 21,  50 => 20,  48 => 19,  44 => 17,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/modules/locale/templates/locale-translation-last-check.html.twig", "D:\\xampp_8_2_12\\htdocs\\drupal-10.2.1\\core\\modules\\locale\\templates\\locale-translation-last-check.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 19, "trans" => 20];
        static $filters = ["escape" => 20, "t" => 22];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'trans'],
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
