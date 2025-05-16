export interface BreadcrumbItemType {
    title: string;
    href?: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: any;
    children?: NavItem[];
}

export interface SharedData {
    url: string;
    [key: string]: any;
}
