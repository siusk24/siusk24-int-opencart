<?php

namespace Mijora\S24IntOpencart;

class Params
{
    const VERSION = '1.0.0';

    const PREFIX = 's24_int_m_';

    const DIR_MAIN = DIR_SYSTEM . 'library/s24_int_m/';

    const GIT_VERSION_CHECK = 'https://api.github.com/repos/siusk24/siusk24-int-opencart/releases/latest';
    const GIT_URL = 'https://github.com/siusk24/siusk24-int-opencart/releases/latest';
    const GIT_CHECK_EVERY_HOURS = 24; // how often to check git for version. Default 24h

    const API_URL = 'https://www.siusk24.lt/api/v1';
    const API_URL_TEST = 'https://demo.siusk24.lt/api/v1';

    const API_IMAGES_URL = 'https://www.siusk24.lt/';
    const API_IMAGES_URL_TEST = 'https://demo.siusk24.lt/';

    const BASE_MOD_XML = 's24_int_m_base.ocmod.xml';
    const BASE_MOD_XML_SOURCE_DIR = self::DIR_MAIN . 'ocmod/'; // should have subfolders based on oc version
    const BASE_MOD_XML_SYSTEM = DIR_SYSTEM . self::BASE_MOD_XML;

    const MOD_SOURCE_DIR_OC_3_0 = '3_0/';
    const MOD_SOURCE_DIR_OC_2_3 = '2_3/';

    const COUNTRY_CHECK_TIME = 24 * 60 * 60; // 24h
    const COUNTRY_CHECK_TIME_RETRY = 60 * 60; // 1h
}
