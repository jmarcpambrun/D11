<?php
namespace Drupal\webform_quiz\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform_quiz\QuizResults;
use Mpdf\Mpdf;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class SubmissionReport extends ControllerBase
{

    protected $twig;

    public function __construct($twig)
    {
        $this->twig = $twig;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('twig')
        );
    }

    public function downloadSubmissionReport($webform_submission_id)
    {

        $webform_submission = QuizResults::loadSubmission($webform_submission_id);
        if (!$webform_submission) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $res = new QuizResults($webform_submission);

        $temp_file = null;
        $temp_file_name = null;
        try {
          $temp_file = $res->generateReportFromSubmission();
          $temp_file_name = $temp_file['name'];
          $temp_file = $temp_file['file'];
        } catch (\Mpdf\MpdfException $e) {
            return new \Symfony\Component\HttpFoundation\Response($e->getMessage(), \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new BinaryFileResponse($temp_file);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $temp_file_name);
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
