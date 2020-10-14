<?php

namespace App\Validator\Constraints;

use App\Entity\Integration;
use App\Exception\RetailcrmApiException;
use App\Service\ApiClientManager;
use RetailCrm\Exception\CurlException;
use RetailCrm\Exception\InvalidJsonException;
use RetailCrm\Exception\LimitException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiAccessValidator extends ConstraintValidator
{
    /**
     * @var ApiClientManager
     */
    private $apiClientManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array
     */
    private $requiredMethods = [
        '/api/integration-modules/{code}',
        '/api/integration-modules/{code}/edit',
        '/api/payment/(updateInvoice|check)',
    ];

    public function __construct(ApiClientManager $apiClientManager, TranslatorInterface $translator)
    {
        $this->apiClientManager = $apiClientManager;
        $this->translator = $translator;
    }

    /**
     * @param Integration $integration
     */
    public function validate($integration, Constraint $constraint)
    {
        if (!($constraint instanceof ApiAccess)) {
            throw new UnexpectedTypeException($constraint, ApiAccess::class);
        }

        try {
            $credentials = $this->apiClientManager->getCredentials($integration);
        } catch (CurlException $e) {
            $this->context->buildViolation($this->translator->trans('validator.api_access.exception.curl'))
                ->atPath('crmUrl')
                ->addViolation();

            return;
        } catch (LimitException $e) {
            $this->context->buildViolation($this->translator->trans('validator.api_access.exception.service_unavailable'))
                ->atPath('crmUrl')
                ->addViolation();

            return;
        } catch (InvalidJsonException $e) {
            $this->context->buildViolation($this->translator->trans('validator.api_access.exception.invalid_json'))
                ->atPath('crmUrl')
                ->addViolation();

            return;
        } catch (\InvalidArgumentException $e) {
            $this->context->buildViolation($this->translator->trans('validator.api_access.exception.requires_https'))
                ->atPath('crmUrl')
                ->addViolation();

            return;
        } catch (RetailcrmApiException $e) {
            $this->context->buildViolation($this->translator->trans('validator.api_access.exception.wrong_apikey'))
                ->atPath('crmApiKey')
                ->addViolation();

            return;
        }

        foreach ($this->requiredMethods as $method) {
            if (!in_array($method, $credentials)) {
                $this->context->buildViolation($this->translator->trans('validator.api_access.access_denied', ['%method%' => $method]))
                    ->atPath('crmApiKey')
                    ->addViolation();
            }
        }
    }
}
