<?php

use Analytics\TimeFrame;
use chsxf\MFX\Attributes\PreRouteCallback;
use chsxf\MFX\Attributes\RequiredRequestMethod;
use chsxf\MFX\Attributes\Route;
use chsxf\MFX\HttpStatusCodes;
use chsxf\MFX\RequestMethod;
use chsxf\MFX\RequestResult;
use chsxf\MFX\Routers\BaseRouteProvider;

final class Analytics extends BaseRouteProvider
{
    #[Route, RequiredRequestMethod(RequestMethod::GET), PreRouteCallback('GlobalCallbacks::googleChartsPreRouteCallback')]
    public function show(): RequestResult
    {
        $requestResultData = [];

        $analyticsConfig = $this->serviceProvider->getConfigService()->getValue('analytics');
        if (!empty($analyticsConfig['enabled'])) {
            $this->serviceProvider->getScriptService()->add('/js/timeFrameSelector.js', defer: true);

            $validator = TimeFrame::buildAnalyticsTimeFrameSelectorValidator([TimeFrame::realtime]);
            $validator->validate($_GET, silent: true);

            $timeFrame = TimeFrame::tryFrom(intval($validator->getFieldValue(TimeFrame::TIMEFRAME_FIELD, true))) ?? TimeFrame::lastDays7;

            $graphDataURL = "{$analyticsConfig['endpoint']}/DataExport.queryDomain?";
            $queryParams = [
                'days' => $timeFrame->value,
                'domain' => $analyticsConfig['domain']
            ];
            $graphDataURL .= http_build_query($queryParams);

            $mostViewedPagesURL = "{$analyticsConfig['endpoint']}/DataExport.mostViewedPages?" . http_build_query($queryParams);

            $requestResultData['analytics'] = [
                'access_key' => $analyticsConfig['access_key'],
                'graphDataURL' => $graphDataURL,
                'mostViewedPagesURL' => $mostViewedPagesURL,
                'validator' => $validator,
                'hAxisTitle' => 'Date',
                'cover_base_url' => $this->serviceProvider->getConfigService()->getValue(('covers.base_url'))
            ];
        }

        return new RequestResult(data: $requestResultData);
    }

    #[Route, RequiredRequestMethod(RequestMethod::POST)]
    public function resolve(): RequestResult
    {
        $rawBody = file_get_contents('php://input');

        try {
            $sortedRows = json_decode($rawBody, flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return RequestResult::buildStatusRequestResult(HttpStatusCodes::badRequest);
        }

        $slugs = [];
        foreach ($sortedRows as $row) {
            if (preg_match('/^\w+$/', $row[0])) {
                $slugs[] = $row[0];
            }
        }

        if (!empty($slugs)) {
            $dbConn = $this->serviceProvider->getDatabaseService()->open();

            $marks = implode(',', array_pad([], count($slugs), '?'));
            $sql = "SELECT `slug`, `id`, `title`
                        FROM `albums`
                        WHERE `slug` IN ({$marks})";
            if (($metadataBySlug = $dbConn->getIndexed($sql, 'slug', \PDO::FETCH_ASSOC, $slugs)) === false) {
                return RequestResult::buildStatusRequestResult(HttpStatusCodes::internalServerError);
            }
        } else {
            $metadataBySlug = [];
        }

        foreach ($sortedRows as &$row) {
            if (array_key_exists($row[0], $metadataBySlug)) {
                $metadata = $metadataBySlug[$row[0]];
                $row[] = $metadata['id'];
                $row[] = $metadata['title'];
            } else {
                $row[] = null;
                $row[] = null;
            }
        }

        return RequestResult::buildJSONRequestResult(['resolved' => $sortedRows]);
    }
}
