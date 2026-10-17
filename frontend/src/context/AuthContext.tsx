import { useQuery, useQueryClient } from "@tanstack/react-query";
import { isAxiosError } from "axios";
import { useEffect } from "react";
import type { ReactNode } from "react";
import { api } from "../api/client";
import type { ApiEnvelope, User } from "../types/api";
import { AuthContext } from "./auth-context";

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();
    const { data: user = null, isLoading } = useQuery({
        queryKey: ["auth", "me"],
        queryFn: async () => {
            try {
                return (await api.get<ApiEnvelope<User>>("/auth/me")).data.data;
            } catch (error) {
                // Chỉ 401 nghĩa là chưa đăng nhập; lỗi mạng/5xx thì giữ nguyên người dùng hiện tại.
                if (isAxiosError(error) && error.response?.status === 401) {
                    return null;
                }
                throw error;
            }
        },
        retry: false,
    });

    // Phiên hết hạn giữa chừng (401/419 ở request bất kỳ): xoá người dùng để route guard đưa về /login.
    useEffect(() => {
        const id = api.interceptors.response.use(undefined, (error: unknown) => {
            const status = isAxiosError(error) ? error.response?.status : undefined;
            const url = isAxiosError(error) ? (error.config?.url ?? "") : "";
            if ((status === 401 || status === 419) && !url.startsWith("/auth/")) {
                queryClient.setQueryData(["auth", "me"], null);
            }
            return Promise.reject(error);
        });
        return () => api.interceptors.response.eject(id);
    }, [queryClient]);

    const dropUserData = () =>
        queryClient.removeQueries({
            predicate: (query) => query.queryKey[0] !== "auth",
        });

    const authenticate = async (
        path: string,
        payload: Record<string, unknown>,
    ) => {
        const nextUser = (await api.post<ApiEnvelope<User>>(path, payload)).data
            .data;
        // Bỏ dữ liệu của phiên trước (kể cả khi phiên cũ hết hạn mà chưa đăng xuất).
        dropUserData();
        queryClient.setQueryData(["auth", "me"], nextUser);
        return nextUser;
    };

    const logout = async () => {
        try {
            // Yêu cầu backend hủy session.
            await api.post("/auth/logout");
        } catch {
            // Phiên đã hết hạn/CSRF lệch: server coi như đã đăng xuất, giao diện vẫn phải thoát.
        } finally {
            /*
             * Phải đặt user = null TRƯỚC khi dọn cache: queryClient.clear() chỉ gỡ query
             * khỏi cache mà không báo cho observer, nên header vẫn hiện người dùng cũ
             * cho tới khi tải lại trang.
             */
            queryClient.setQueryData(["auth", "me"], null);
            dropUserData();
        }
    };

    return (
        <AuthContext.Provider
            value={{
                user,
                isLoading,
                login: (payload) => authenticate("/auth/login", payload),
                register: (payload) => authenticate("/auth/register", payload),
                logout,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}
